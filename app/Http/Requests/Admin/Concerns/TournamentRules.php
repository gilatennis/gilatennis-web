<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\TennisLevel;

trait TournamentRules
{
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'dates' => ['required', 'string', 'max:255'],
            'start' => ['required', 'string', 'max:255'],
            'end' => ['nullable', 'string', 'max:255'],
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
            'status' => ['required', 'in:draft,open,closed,completed'],
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
}
