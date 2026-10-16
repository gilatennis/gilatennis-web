<?php

namespace App\Console\Commands;

use App\Models\BracketGroupEntrant;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentRegistrationPlayer;
use App\Services\TournamentRegistrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('tournament:sync-team-rosters {tournament : Tournament slug} {--apply : Persist the changes (default is a dry run)} {--prune : Delete registrations that are not in the draw and not a kept team} {--path= : Path to the roster data file}')]
#[Description('Align team names and member names with the organiser roster (defaults to a dry run)')]
class SyncTeamRostersCommand extends Command
{
    public function handle(TournamentRegistrationService $service): int
    {
        $path = $this->option('path') ?: database_path('data/gilatennis-teams-2026.php');

        if (! is_string($path) || ! is_file($path)) {
            $this->error("Roster data file not found: {$path}");

            return self::FAILURE;
        }

        /** @var array{tournament: string, teams: array<string, array<int, array{name: string, gender: string, from?: array<int, string>}>>} $data */
        $data = require $path;

        $tournament = Tournament::where('slug', $data['tournament'])->first();

        if ($tournament === null) {
            $this->error("Tournament not found: {$data['tournament']}");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $prune = (bool) $this->option('prune');

        $registrations = TournamentRegistration::with(['team', 'players', 'division'])
            ->where('tournament_id', $tournament->id)
            ->get()
            ->filter(fn (TournamentRegistration $r): bool => $r->team instanceof Team);

        $drawnIds = $this->drawnRegistrationIds($registrations->pluck('id')->all());

        $groups = [];
        foreach ($registrations as $registration) {
            $groups[$this->normalize($registration->team->name)][] = $registration;
        }

        $keptIds = [];
        $errors = 0;

        foreach ($data['teams'] as $teamName => $roster) {
            $group = $groups[$this->normalize($teamName)] ?? [];

            if ($group === []) {
                $this->warn("No registration found for team \"{$teamName}\" — skipped.");
                $errors++;

                continue;
            }

            $primary = $this->pickPrimary(collect($group), $drawnIds);
            $keptIds[] = $primary->id;

            $report = $this->planTeam($primary, $teamName, $roster);
            $composition = $this->compositionErrors($primary->division, $report['payload']);

            $this->newLine();
            $this->line("<comment>{$teamName}</comment> (reg #{$primary->id})");

            foreach ($report['changes'] as $change) {
                $this->line("  - {$change}");
            }

            if ($report['changes'] === []) {
                $this->line('  (no changes)');
            }

            $summary = sprintf('  %d players — %dF/%dM', count($report['payload']), $composition['female'], $composition['male']);
            $this->line(empty($composition['errors']) ? "<info>{$summary}</info>" : "<error>{$summary}</error>");

            foreach ($composition['errors'] as $error) {
                $this->error("    {$error}");
                $errors++;
            }

            if ($apply && empty($composition['errors'])) {
                $primary->team?->update(['name' => $teamName]);
                $service->syncPlayers($primary, $report['payload']);
            }
        }

        if ($prune) {
            $this->prune($registrations, $keptIds, $drawnIds, $service, $apply);
        }

        $this->newLine();

        if ($errors > 0) {
            $this->error($apply
                ? 'Aborted: composition errors must be fixed before applying.'
                : "Dry run finished with {$errors} problem(s) — nothing written.");

            return self::FAILURE;
        }

        $this->info($apply ? 'Applied.' : 'Dry run — nothing written. Re-run with --apply to persist.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{name: string, gender: string, from?: array<int, string>}>  $roster
     * @return array{payload: array<int, array<string, mixed>>, changes: array<int, string>}
     */
    private function planTeam(TournamentRegistration $registration, string $teamName, array $roster): array
    {
        $existing = $registration->players;
        $usedIds = [];
        $payload = [];
        $changes = [];

        if ($registration->team?->name !== $teamName) {
            $changes[] = "team name \"{$registration->team?->name}\" -> \"{$teamName}\"";
        }

        foreach ($roster as $entry) {
            $targets = collect([$entry['name'], ...($entry['from'] ?? [])])
                ->map(fn (string $name): string => $this->normalize($name));

            $match = $existing->first(fn (TournamentRegistrationPlayer $player): bool => ! in_array($player->id, $usedIds, true)
                && $targets->contains($this->normalize($player->name)));

            if ($match instanceof TournamentRegistrationPlayer) {
                $usedIds[] = $match->id;

                if ($match->name !== $entry['name']) {
                    $changes[] = "rename \"{$match->name}\" -> \"{$entry['name']}\"";
                }

                $payload[] = [
                    'id' => $match->id,
                    'name' => $entry['name'],
                    'gender' => $match->gender ?: $entry['gender'],
                    'email' => $match->email,
                    'phone' => $match->phone,
                    'date_of_birth' => $match->date_of_birth,
                    'rating_type' => $match->rating_type,
                    'rating_value' => $match->rating_value,
                    'level' => $match->level,
                ];

                continue;
            }

            $changes[] = "add \"{$entry['name']}\" ({$entry['gender']})";
            $payload[] = ['name' => $entry['name'], 'gender' => $entry['gender']];
        }

        foreach ($existing as $player) {
            if (! in_array($player->id, $usedIds, true)) {
                $changes[] = "remove \"{$player->name}\"";
            }
        }

        return ['payload' => $payload, 'changes' => $changes];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array{female: int, male: int, errors: array<int, string>}
     */
    private function compositionErrors(?Division $division, array $payload): array
    {
        $female = collect($payload)->where('gender', 'female')->count();
        $male = collect($payload)->where('gender', 'male')->count();
        $count = count($payload);
        $errors = [];

        if ($division === null || $division->participation_category !== 'team') {
            return ['female' => $female, 'male' => $male, 'errors' => $errors];
        }

        if ($division->min_team_size && $count < $division->min_team_size) {
            $errors[] = "needs at least {$division->min_team_size} players (has {$count})";
        }

        if ($division->max_team_size && $count > $division->max_team_size) {
            $errors[] = "allows at most {$division->max_team_size} players (has {$count})";
        }

        if ($division->min_female_players && $female < $division->min_female_players) {
            $errors[] = "needs at least {$division->min_female_players} female (has {$female})";
        }

        if ($division->min_male_players && $male < $division->min_male_players) {
            $errors[] = "needs at least {$division->min_male_players} male (has {$male})";
        }

        return ['female' => $female, 'male' => $male, 'errors' => $errors];
    }

    /**
     * @param  Collection<int, TournamentRegistration>  $registrations
     * @param  array<int, int>  $keptIds
     * @param  array<int, int>  $drawnIds
     */
    private function prune(Collection $registrations, array $keptIds, array $drawnIds, TournamentRegistrationService $service, bool $apply): void
    {
        $this->newLine();
        $this->line('<comment>Prune</comment>');

        $removed = 0;

        foreach ($registrations as $registration) {
            if (in_array($registration->id, $keptIds, true)) {
                continue;
            }

            if ($registration->isWithdrawn() || $registration->payment_status === 'refunded') {
                continue;
            }

            $reason = in_array($registration->id, $drawnIds, true) ? 'already in the draw (untouched team)' : 'not in the draw';

            $this->line("  - delete reg #{$registration->id} \"{$registration->team?->name}\" ({$reason})");
            $removed++;

            if ($apply) {
                $service->delete($registration);
            }
        }

        if ($removed === 0) {
            $this->line('  (nothing to prune)');
        }
    }

    /**
     * @param  Collection<int, TournamentRegistration>  $group
     * @param  array<int, int>  $drawnIds
     */
    private function pickPrimary(Collection $group, array $drawnIds): TournamentRegistration
    {
        $rank = fn (TournamentRegistration $r): array => [
            in_array($r->id, $drawnIds, true) ? 0 : 1,
            $r->payment_status === 'paid' ? 0 : 1,
            $r->id,
        ];

        return $group->sort(fn (TournamentRegistration $a, TournamentRegistration $b): int => $rank($a) <=> $rank($b))->first();
    }

    /**
     * @param  array<int, int>  $registrationIds
     * @return array<int, int>
     */
    private function drawnRegistrationIds(array $registrationIds): array
    {
        return BracketGroupEntrant::whereIn('tournament_registration_id', $registrationIds)
            ->pluck('tournament_registration_id')
            ->merge(BracketSlot::whereIn('entrant_a_id', $registrationIds)->pluck('entrant_a_id'))
            ->merge(BracketSlot::whereIn('entrant_b_id', $registrationIds)->pluck('entrant_b_id'))
            ->unique()
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value)) ?? '';
    }
}
