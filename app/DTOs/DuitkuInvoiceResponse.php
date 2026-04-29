<?php

namespace App\DTOs;

class DuitkuInvoiceResponse
{
    public function __construct(
        public readonly string $merchantCode,
        public readonly string $reference,
        public readonly string $paymentUrl,
        public readonly string $statusCode,
        public readonly string $statusMessage,
    ) {}
}
