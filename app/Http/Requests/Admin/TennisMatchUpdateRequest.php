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
            'side_a_name' => ['sometimes', 'string', 'max:60'],
            'side_b_name' => ['sometimes', 'string', 'max:60'],
            'sets_to_win' => ['sometimes', 'integer', 'between:1,3'],
            'games_to_win' => ['sometimes', 'integer', 'between:1,8'],
            'extend_at_tie' => ['sometimes', 'boolean'],
            'tiebreak_to' => ['sometimes', 'integer', 'between:1,21'],
            'status' => ['sometimes', Rule::enum(MatchStatus::class)],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
