<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the optional format overrides when starting a tie in a singles or
 * doubles division. Exactly one rubber is created per slot.
 */
class BracketSlotStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'side_a_name' => ['nullable', 'string', 'max:60'],
            'side_b_name' => ['nullable', 'string', 'max:60'],
            'sets_to_win' => ['nullable', 'integer', 'between:1,3'],
            'games_to_win' => ['nullable', 'integer', 'between:2,8'],
            'extend_at_tie' => ['nullable', 'boolean'],
            'tiebreak_to' => ['nullable', 'integer', 'between:1,21'],
        ];
    }
}
