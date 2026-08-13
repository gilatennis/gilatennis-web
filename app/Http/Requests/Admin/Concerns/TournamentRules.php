<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\TennisLevel;
use App\Enums\TournamentStatus;
use App\Models\DivisionPlayerFieldRequirement;
use Illuminate\Validation\Rule;

trait TournamentRules
{
    protected function baseRules(): array
    {
        $fieldKeys = array_keys(DivisionPlayerFieldRequirement::FIELDS);

        return [
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'dates' => ['required', 'string', 'max:255'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'city' => ['required', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'surface' => ['required', 'in:hard,clay,grass,indoor_hard'],
            'ball_type' => ['nullable', 'string', 'max:255'],
            'venue_type' => ['nullable', 'in:indoor,outdoor,both'],
            'perks' => ['nullable', 'array'],
            'perks.*.key' => ['required', 'string', 'max:255'],
            'perks.*.value' => ['required', 'string', 'max:500'],
            'registration_closes' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'status' => ['required', Rule::enum(TournamentStatus::class)],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],

            'divisions' => ['nullable', 'array'],
            'divisions.*.name' => ['required', 'string', 'max:255'],
            'divisions.*.slug' => ['nullable', 'string', 'max:255'],
            'divisions.*.participation_category' => ['required', 'in:singles,doubles,team'],
            'divisions.*.draw_size' => ['required', 'integer', 'min:1'],
            'divisions.*.fee' => ['required', 'numeric', 'min:0'],
            'divisions.*.format' => ['nullable', 'string', 'max:255'],
            'divisions.*.gender' => ['nullable', 'in:mens,womens,mixed,coed'],
            'divisions.*.rating_type' => ['nullable', 'string', 'max:20'],
            'divisions.*.rating_min' => ['nullable', 'numeric'],
            'divisions.*.rating_max' => ['nullable', 'numeric'],
            'divisions.*.min_age' => ['nullable', 'integer', 'min:0'],
            'divisions.*.max_age' => ['nullable', 'integer', 'min:0'],
            'divisions.*.min_team_size' => ['nullable', 'integer', 'min:1'],
            'divisions.*.max_team_size' => ['nullable', 'integer', 'min:1'],
            'divisions.*.min_female_players' => ['nullable', 'integer', 'min:0'],
            'divisions.*.min_male_players' => ['nullable', 'integer', 'min:0'],
            'divisions.*.level_requirements' => ['nullable', 'array'],
            'divisions.*.level_requirements.*' => ['nullable', 'integer', 'min:0'],
            'divisions.*.player_field_requirements' => ['nullable', 'array'],
            'divisions.*.player_field_requirements.*.field_key' => ['required', 'string', 'in:'.implode(',', $fieldKeys)],
            'divisions.*.player_field_requirements.*.required' => ['required', 'boolean'],
            'divisions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'divisions.*.prizes' => ['nullable', 'array'],
            'divisions.*.prizes.*.place' => ['required', 'string', 'max:255'],
            'divisions.*.prizes.*.value' => ['required', 'string', 'max:255'],
            'divisions.*.prizes.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'schedule' => ['nullable', 'array'],
            'schedule.*.day_label' => ['required', 'string', 'max:255'],
            'schedule.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'schedule.*.items' => ['nullable', 'array'],
            'schedule.*.items.*.label' => ['required', 'string', 'max:255'],
            'schedule.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'rules' => ['nullable', 'array'],
            'rules.*.body' => ['required', 'string'],
            'rules.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function levelRequirementKeys(): array
    {
        return TennisLevel::values();
    }

    protected function validateLevelRequirementKeys($validator): void
    {
        $validKeys = $this->levelRequirementKeys();

        foreach ($this->input('divisions', []) as $i => $division) {
            $reqs = $division['level_requirements'] ?? null;

            if (! is_array($reqs)) {
                continue;
            }

            foreach (array_keys($reqs) as $key) {
                if (! in_array($key, $validKeys, true)) {
                    $validator->errors()->add(
                        "divisions.{$i}.level_requirements.{$key}",
                        "Invalid level '{$key}'. Allowed: ".implode(', ', $validKeys).'.',
                    );
                }
            }
        }
    }

    protected function validatePlayerFieldRequirements($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('divisions', []) as $i => $division) {
                $pfr = $division['player_field_requirements'] ?? null;

                if (! is_array($pfr)) {
                    continue;
                }

                $seen = [];

                foreach ($pfr as $j => $row) {
                    $key = $row['field_key'] ?? null;

                    if ($key === null) {
                        continue;
                    }

                    if (isset($seen[$key])) {
                        $validator->errors()->add(
                            "divisions.{$i}.player_field_requirements.{$j}.field_key",
                            "Duplicate field '{$key}'.",
                        );
                    }

                    $seen[$key] = true;

                    if ($key === 'name' && array_key_exists('required', $row) && ! $row['required']) {
                        $validator->errors()->add(
                            "divisions.{$i}.player_field_requirements.{$j}.required",
                            'The name field is always required.',
                        );
                    }
                }
            }
        });
    }
}
