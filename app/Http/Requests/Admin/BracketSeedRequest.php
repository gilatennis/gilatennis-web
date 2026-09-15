<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BracketSeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entrant_ids' => ['required', 'array', 'min:2'],
            'entrant_ids.*' => ['integer', 'distinct', 'exists:tournament_registrations,id'],
        ];
    }
}
