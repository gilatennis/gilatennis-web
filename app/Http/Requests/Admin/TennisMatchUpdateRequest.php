<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TennisMatchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MatchStatus::class)],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
