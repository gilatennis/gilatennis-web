<?php

namespace App\Http\Requests\Admin;

use App\Enums\TennisLevel;
use Illuminate\Foundation\Http\FormRequest;

class DivisionValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'participation_category' => ['required', 'in:singles,doubles,team'],
            'draw_size' => ['required', 'integer', 'min:1'],
            'fee' => ['required', 'numeric', 'min:0'],
            'format' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:mens,womens,mixed,coed'],
            'rating_type' => ['nullable', 'string', 'max:20'],
            'rating_min' => ['nullable', 'numeric'],
            'rating_max' => ['nullable', 'numeric'],
            'min_age' => ['nullable', 'integer', 'min:0'],
            'max_age' => ['nullable', 'integer', 'min:0'],
            'min_team_size' => ['nullable', 'integer', 'min:1'],
            'max_team_size' => ['nullable', 'integer', 'min:1'],
            'min_female_players' => ['nullable', 'integer', 'min:0'],
            'min_male_players' => ['nullable', 'integer', 'min:0'],
            'level_requirements' => ['nullable', 'array'],
            'level_requirements.*' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prizes' => ['nullable', 'array'],
            'prizes.*.place' => ['required', 'string', 'max:255'],
            'prizes.*.value' => ['required', 'string', 'max:255'],
            'prizes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reqs = $this->input('level_requirements');

            if (! is_array($reqs)) {
                return;
            }

            $valid = TennisLevel::values();

            foreach (array_keys($reqs) as $key) {
                if (! in_array($key, $valid, true)) {
                    $validator->errors()->add(
                        "level_requirements.{$key}",
                        "Invalid level '{$key}'. Allowed: ".implode(', ', $valid).'.',
                    );
                }
            }
        });
    }
}
