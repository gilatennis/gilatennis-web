<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TournamentRegistration;

trait ShapesRegistration
{
    private static function shapeRegistration(TournamentRegistration $registration, bool $includeDivision = false): array
    {
        $data = [
            'id' => $registration->id,
            'confirmation_code' => $registration->confirmation_code,
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'date_of_birth' => $registration->date_of_birth,
            'payment_status' => $registration->payment_status,
            'payment_reference' => $registration->payment_reference,
            'payment_url' => $registration->payment_url,
            'amount_cents' => $registration->amount_cents,
            'fee' => $registration->fee,
            'currency' => $registration->currency,
            'paid_at' => $registration->paid_at,
            'created_at' => $registration->created_at,
            'team' => $registration->team ? [
                'id' => $registration->team->id,
                'name' => $registration->team->name,
            ] : null,
            'players' => $registration->players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'phone' => $p->phone,
                'gender' => $p->gender,
                'date_of_birth' => $p->date_of_birth,
                'rating_type' => $p->rating_type,
                'rating_value' => $p->rating_value,
                'level' => $p->level,
                'assessment' => $p->assessment ? [
                    'status' => $p->assessment->status->value,
                    'reason' => $p->assessment->reason,
                    'video_url' => $p->assessment->video_url,
                    'notes' => $p->assessment->notes,
                    'assessed_at' => $p->assessment->assessed_at,
                ] : [
                    'status' => 'unreviewed',
                    'reason' => null,
                    'video_url' => null,
                    'notes' => null,
                    'assessed_at' => null,
                ],
            ]),
        ];

        if ($includeDivision) {
            $data['division'] = $registration->division ? [
                'id' => $registration->division->id,
                'name' => $registration->division->name,
            ] : null;
        }

        return $data;
    }
}
