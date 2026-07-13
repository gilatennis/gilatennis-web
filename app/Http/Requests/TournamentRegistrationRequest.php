<?php

namespace App\Http\Requests;

use App\Enums\TennisLevel;
use App\Models\Division;
use App\Models\DivisionPlayerFieldRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TournamentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $division = $this->resolveDivision();
        $isTeam = $division && $division->participation_category === 'team';

        $config = $division
            ? $division->playerFieldConfig()
            : DivisionPlayerFieldRequirement::FIELDS;

        $forceLevel = $division && $division->hasLevelRequirements();
        $forceGender = $division && ($division->min_female_players > 0 || $division->min_male_players > 0);

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
            'email' => [$config['email'] ?? true ? 'required' : 'nullable', 'email', 'max:255'],
            'phone' => [$config['phone'] ?? true ? 'required' : 'nullable', 'string', 'max:50'],
            'gender' => [($config['gender'] ?? true) || $forceGender ? 'required' : 'nullable', 'in:male,female'],
            'date_of_birth' => [$config['dob'] ?? false ? 'required' : 'nullable', 'date', 'before:today'],
            'rating_type' => ['nullable', 'in:UTR,ITN,USTA'],
            'rating_value' => ['nullable', 'numeric'],
            'level' => [($forceLevel || ($config['level'] ?? true)) ? 'required' : 'nullable', new Enum(TennisLevel::class)],
        ];

        $rules = [
            'division_id' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'team_name' => [$isTeam ? 'required' : 'nullable', 'string', 'max:255'],
            'waiver_accepted' => ['required', 'accepted'],
            'players' => ['required', 'array'],
        ];

        foreach ($this->input('players', []) as $i => $_) {
            $base = ($isTeam && $i > 0) ? $teammateBase : $captainBase;

            foreach ($base as $key => $rule) {
                $rules["players.{$i}.{$key}"] = $rule;
            }
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $division = $this->resolveDivision();

            if (! $division) {
                return;
            }

            $playerCount = count($this->input('players', []));

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
                    $femaleCount = collect($this->input('players', []))->where('gender', 'female')->count();
                    $maleCount = collect($this->input('players', []))->where('gender', 'male')->count();

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
                $counts = collect($this->input('players', []))
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
                foreach ($this->input('players', []) as $i => $player) {
                    $val = $player['rating_value'] ?? null;

                    if ($val === null || $val === '') {
                        continue;
                    }

                    $numVal = (float) $val;

                    if ($numVal < (float) $division->rating_min || $numVal > (float) $division->rating_max) {
                        $validator->errors()->add(
                            "players.{$i}.rating_value",
                            "Rating must be between {$division->rating_min} and {$division->rating_max} for {$division->rating_type}."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'players.*.email.email' => 'Each player must have a valid email address.',
        ];
    }

    private function resolveDivision(): ?Division
    {
        return $this->route('tournament')?->divisions()
            ->where('id', $this->input('division_id'))
            ->first();
    }
}
