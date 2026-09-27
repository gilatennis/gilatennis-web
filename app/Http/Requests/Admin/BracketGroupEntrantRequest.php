<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates placing a paid entrant in a group, or moving one between groups.
 * `to_group_id` is only sent when moving; adding an entrant omits it.
 */
class BracketGroupEntrantRequest extends FormRequest
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
            'entrant_id' => ['required', 'integer', 'exists:tournament_registrations,id'],
            'to_group_id' => ['nullable', 'integer', 'exists:bracket_groups,id'],
        ];
    }
}
