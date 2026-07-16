<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlayerAssessmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerAssessmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assessments' => ['required', 'array'],
            'assessments.*.player_id' => ['required', 'integer'],
            'assessments.*.status' => ['required', Rule::enum(PlayerAssessmentStatus::class)],
            'assessments.*.reason' => ['nullable', 'string', 'max:500'],
            'assessments.*.video_url' => ['nullable', 'url', 'max:500'],
            'assessments.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $registration = $this->route('registration');

            if (! $registration) {
                return;
            }

            $validPlayerIds = $registration->players()->pluck('id')->all();
            $seen = [];

            foreach ((array) $this->input('assessments', []) as $index => $row) {
                $pid = $row['player_id'] ?? null;

                if ($pid === null) {
                    continue;
                }

                if (! in_array((int) $pid, $validPlayerIds, true)) {
                    $validator->errors()->add(
                        "assessments.{$index}.player_id",
                        'Player does not belong to this registration.',
                    );

                    continue;
                }

                if (isset($seen[$pid])) {
                    $validator->errors()->add(
                        "assessments.{$index}.player_id",
                        'Duplicate player in assessments.',
                    );
                }

                $seen[$pid] = true;

                if (
                    ($row['status'] ?? null) === PlayerAssessmentStatus::DoesNotMeetLevel->value
                ) {
                    $reason = trim((string) ($row['reason'] ?? ''));

                    if (mb_strlen($reason) < 10) {
                        $validator->errors()->add(
                            "assessments.{$index}.reason",
                            'Reason is required (min 10 characters) when status is Does not meet level.',
                        );
                    }
                }
            }
        });
    }
}
