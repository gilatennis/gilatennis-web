<?php

namespace App\Http\Requests\Admin\Concerns;

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
}
