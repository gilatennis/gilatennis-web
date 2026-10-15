<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The statuses a referee/scorekeeper may set from the scoreboard or the match
 * list: `running` (send to court), `pending` (recall from court), `completed`.
 * Broader transitions (canceled) stay in the admin-only match update endpoint.
 */
class TennisMatchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MatchStatus::class)->only([
                MatchStatus::Pending,
                MatchStatus::Running,
                MatchStatus::Completed,
            ])],
        ];
    }
}
