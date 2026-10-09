<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates placing an entrant into (or clearing) one side of a first-round
 * knockout slot.
 */
class BracketSlotEntrantRequest extends FormRequest
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
            'side' => ['required', Rule::in(['a', 'b'])],
            'entrant_id' => ['nullable', 'integer', 'exists:tournament_registrations,id'],
        ];
    }
}
