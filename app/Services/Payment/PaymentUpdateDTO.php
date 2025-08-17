<?php

namespace App\Services\Payment;

class PaymentUpdateDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $paidAt = null,
        public readonly ?array $payload = null
    ) {}
}