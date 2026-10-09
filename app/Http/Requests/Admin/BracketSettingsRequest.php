<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates toggling a knockout bracket's manual-assignment setting. The toggle
 * is rejected once the draw has left the draft state.
 */
class BracketSettingsRequest extends FormRequest
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
            'manual_assignment' => ['required', 'boolean'],
        ];
    }
}
