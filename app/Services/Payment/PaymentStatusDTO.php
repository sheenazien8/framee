<?php

namespace App\Services\Payment;

class PaymentStatusDTO
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?int $amount = null,
        public readonly ?string $paidAt = null,
        public readonly ?array $metadata = null
    ) {}
}