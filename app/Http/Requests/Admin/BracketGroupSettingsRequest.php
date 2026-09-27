<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates switching a single group between single and double round-robin.
 * The toggle is rejected once the group has started.
 */
class BracketGroupSettingsRequest extends FormRequest
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
            'double_round_robin' => ['required', 'boolean'],
        ];
    }
}
