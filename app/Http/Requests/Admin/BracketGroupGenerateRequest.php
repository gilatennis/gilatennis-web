<?php

namespace App\Http\Requests\Admin;

use App\Services\BracketService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of a round-robin group stage for a division. The group
 * count, advancing winners per group, and single/double round-robin choice are
 * all admin-controlled at generate time.
 */
class BracketGroupGenerateRequest extends FormRequest
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
            'group_count' => ['required', 'integer', 'between:2,16'],
            'advance_counts' => ['required', 'array'],
            'advance_counts.*' => ['required', 'integer', 'between:1,8'],
            'double_round_robin' => ['sometimes', 'boolean'],
            'seeding_mode' => ['required', Rule::in([
                BracketService::SEEDING_AUTO,
                BracketService::SEEDING_MANUAL,
            ])],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $groupCount = (int) $this->input('group_count');
                $advanceCounts = (array) $this->input('advance_counts', []);

                if ($groupCount > 0 && count($advanceCounts) !== $groupCount) {
                    $validator->errors()->add(
                        'advance_counts',
                        'Provide one advancing-winner count per group.',
                    );
                }
            },
        ];
    }
}
