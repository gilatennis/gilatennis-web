<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TennisMatchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'side_a_name' => ['required', 'string', 'max:60'],
            'side_b_name' => ['required', 'string', 'max:60'],
            'sets_to_win' => ['required', 'integer', 'between:1,3'],
            'games_to_win' => ['required', 'integer', 'between:1,8'],
            'extend_at_tie' => ['required', 'boolean'],
            'tiebreak_to' => ['required', 'integer', 'between:1,21'],
        ];
    }
}
