<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BracketSlotUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'winner_entrant_id' => ['nullable', 'integer', 'exists:tournament_registrations,id'],
        ];
    }
}
