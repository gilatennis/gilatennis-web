<?php

namespace App\Events;

use App\Models\TournamentRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TournamentRegistration $registration,
    ) {}
}
