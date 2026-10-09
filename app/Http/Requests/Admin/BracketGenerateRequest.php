<?php

namespace App\Http\Requests\Admin;

use App\Services\BracketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BracketGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seeding_mode' => ['required', Rule::in([
                BracketService::SEEDING_AUTO,
                BracketService::SEEDING_MANUAL,
            ])],
            'manual_assignment' => ['sometimes', 'boolean'],
        ];
    }
}
