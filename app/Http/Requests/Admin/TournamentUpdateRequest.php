<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\TournamentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentUpdateRequest extends FormRequest
{
    use TournamentRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');

        return array_merge($this->baseRules(), [
            'slug' => ['required', 'string', 'max:255', Rule::unique('tournaments', 'slug')->ignore($tournament->id)],
            'divisions.*.id' => ['nullable', 'integer', 'exists:divisions,id'],
            'divisions.*.prizes.*.id' => ['nullable', 'integer', 'exists:tournament_prizes,id'],
            'schedule.*.id' => ['nullable', 'integer', 'exists:tournament_schedule_days,id'],
            'schedule.*.items.*.id' => ['nullable', 'integer', 'exists:tournament_schedule_items,id'],
            'rules.*.id' => ['nullable', 'integer', 'exists:tournament_rules,id'],
        ]);
    }

    public function withValidator($validator): void
    {
        $this->validateLevelRequirementKeys($validator);
    }
}
