<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the winners picked to advance from a single round-robin group in a
 * group-stage division. The ids are ordered by seed within the group.
 */
class BracketGroupWinnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'winner_ids' => ['required', 'array', 'min:1'],
            'winner_ids.*' => ['required', 'integer', 'distinct', 'exists:tournament_registrations,id'],
        ];
    }
}
