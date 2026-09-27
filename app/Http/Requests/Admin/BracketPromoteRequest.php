<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates promotion of the picked group winners into a division's
 * single-elimination bracket. The ids are given in knockout seed order.
 */
class BracketPromoteRequest extends FormRequest
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
            'winner_ids' => ['required', 'array', 'min:2'],
            'winner_ids.*' => ['required', 'integer', 'distinct', 'exists:tournament_registrations,id'],
            'seed_pairs_apart' => ['sometimes', 'boolean'],
        ];
    }
}
