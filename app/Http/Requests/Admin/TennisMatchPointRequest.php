<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TennisMatchPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'side' => ['required', 'integer', Rule::in([1, 2])],
            'shot' => ['nullable', 'string', Rule::in([
                'Ace',
                'Winner',
                'Unforced error',
                'Forced error',
                'Double fault',
            ])],
        ];
    }
}
