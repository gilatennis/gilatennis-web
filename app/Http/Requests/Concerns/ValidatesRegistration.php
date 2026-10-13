<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TennisLevel;
use App\Models\Division;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Shared registration validation: the per-player field rules derived from a
 * division's participation category and player-field requirements, plus the
 * cross-field composition checks (team size, gender mix, levels, ratings).
 *
 * Used by both the public registration request and the admin CRUD requests so
 * the two paths can never drift apart.
 */
trait ValidatesRegistration
{
    private ?Division $resolvedDivision = null;

    private bool $divisionResolved = false;

    /**
     * Rules for the `players.*` array, indexed by position. Index 0 is the
     * captain (always strict); later indexes are teammates whose required
     * fields depend on the division's configuration.
     *
     * @return array<string, mixed>
     */
    protected function playerRules(Division $division, bool $isTeam): array
    {
        $config = $division->playerFieldConfig();
        $forceLevel = $division->hasLevelRequirements();
        $forceGender = $division->min_female_players > 0 || $division->min_male_players > 0;

        $captainBase = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'rating_type' => ['nullable', 'in:UTR,ITN,USTA'],
            'rating_value' => ['nullable', 'numeric'],
            'level' => ['nullable', new Enum(TennisLevel::class)],
        ];

        $teammateBase = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [($config['email'] ?? true) ? 'required' : 'nullable', 'email', 'max:255'],
            'phone' => [($config['phone'] ?? true) ? 'required' : 'nullable', 'string', 'max:50'],
            'gender' => [($config['gender'] ?? true) || $forceGender ? 'required' : 'nullable', 'in:male,female'],
            'date_of_birth' => [($config['dob'] ?? false) ? 'required' : 'nullable', 'date', 'before:today'],
            'rating_type' => ['nullable', 'in:UTR,ITN,USTA'],
            'rating_value' => ['nullable', 'numeric'],
            'level' => [($forceLevel || ($config['level'] ?? true)) ? 'required' : 'nullable', new Enum(TennisLevel::class)],
        ];

        $rules = [];

        foreach ((array) $this->input('players', []) as $i => $_) {
            $base = ($isTeam && $i > 0) ? $teammateBase : $captainBase;

            foreach ($base as $key => $rule) {
                $rules["players.{$i}.{$key}"] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Resolve the target division from the route tournament and the submitted
     * `division_id`. Returns null when either is absent or the division does
     * not belong to the tournament.
     */
    public function resolveRegistrationDivision(): ?Division
    {
        if ($this->divisionResolved) {
            return $this->resolvedDivision;
        }

        $this->divisionResolved = true;

        $tournament = $this->route('tournament');

        if ($tournament === null || ! $this->filled('division_id')) {
            return $this->resolvedDivision = null;
        }

        return $this->resolvedDivision = $tournament->divisions()
            ->where('id', $this->input('division_id'))
            ->first();
    }

    /**
     * Cross-field checks that plain rules cannot express: roster size, gender
     * composition, level requirements and rating bounds.
     */
    protected function validatePlayerComposition(Validator $validator, ?Division $division): void
    {
        if (! $division) {
            return;
        }

        $players = $this->input('players', []);
        $playerCount = count($players);

        if ($division->participation_category === 'singles' && $playerCount !== 1) {
            $validator->errors()->add('players', 'Singles division requires exactly 1 player.');
        }

        if ($division->participation_category === 'doubles' && $playerCount !== 2) {
            $validator->errors()->add('players', 'Doubles division requires exactly 2 players.');
        }

        if ($division->participation_category === 'team') {
            $min = $division->min_team_size;
            $max = $division->max_team_size;

            if ($min && $playerCount < $min) {
                $validator->errors()->add('players', "Team requires at least {$min} players.");
            }

            if ($max && $playerCount > $max) {
                $validator->errors()->add('players', "Team allows at most {$max} players.");
            }

            if ($division->min_female_players || $division->min_male_players) {
                $femaleCount = collect($players)->where('gender', 'female')->count();
                $maleCount = collect($players)->where('gender', 'male')->count();

                if ($division->min_female_players && $femaleCount < $division->min_female_players) {
                    $validator->errors()->add(
                        'players',
                        "Team requires at least {$division->min_female_players} female player(s).",
                    );
                }

                if ($division->min_male_players && $maleCount < $division->min_male_players) {
                    $validator->errors()->add(
                        'players',
                        "Team requires at least {$division->min_male_players} male player(s).",
                    );
                }
            }
        }

        if ($division->hasLevelRequirements()) {
            $counts = collect($players)
                ->groupBy(fn ($p) => $p['level'] ?? '__none__')
                ->map->count();

            foreach (TennisLevel::cases() as $level) {
                $required = $division->getLevelRequirement($level);

                if ($required <= 0) {
                    continue;
                }

                $have = (int) ($counts[$level->value] ?? 0);

                if ($have < $required) {
                    $validator->errors()->add(
                        'players',
                        "Team requires at least {$required} {$level->label()} player(s); have {$have}.",
                    );
                }
            }
        }

        if ($division->hasRatingConstraints()) {
            foreach ($players as $i => $player) {
                $val = $player['rating_value'] ?? null;

                if ($val === null || $val === '') {
                    continue;
                }

                $numVal = (float) $val;

                if ($numVal < (float) $division->rating_min || $numVal > (float) $division->rating_max) {
                    $validator->errors()->add(
                        "players.{$i}.rating_value",
                        "Rating must be between {$division->rating_min} and {$division->rating_max} for {$division->rating_type}.",
                    );
                }
            }
        }
    }
}
