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
            'state' => ['sometimes', 'array'],
            'state.server' => ['nullable', 'integer', Rule::in([1, 2])],
            'state.first_server' => ['nullable', 'integer', Rule::in([1, 2])],
            'state.set_a' => ['integer', 'between:0,3'],
            'state.set_b' => ['integer', 'between:0,3'],
            'state.game_a' => ['integer', 'between:0,5'],
            'state.game_b' => ['integer', 'between:0,5'],
            'state.tiebreak' => ['boolean'],
            'state.tb_a' => ['integer', 'between:0,30'],
            'state.tb_b' => ['integer', 'between:0,30'],
            'state.tb_first' => ['nullable', 'integer', Rule::in([1, 2])],
            'state.sets' => ['array'],
            'state.sets.*.a' => ['integer', 'between:0,99'],
            'state.sets.*.b' => ['integer', 'between:0,99'],
            'state.win' => ['nullable', 'integer', Rule::in([1, 2])],
        ];
    }
}
