<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PhotoSession;
use Illuminate\Http\Request;

class XenditDriver implements PaymentGateway
{
    public function createQrPayment(PhotoSession $session): Payment
    {
        // TODO: Implement Xendit QRIS integration
        throw new \Exception('Xendit driver not implemented yet');
    }

    public function getStatus(Payment $payment): PaymentStatusDTO
    {
        // TODO: Implement Xendit status check
        throw new \Exception('Xendit driver not implemented yet');
    }

    public function handleWebhook(Request $request): PaymentUpdateDTO
    {
        // TODO: Implement Xendit webhook handling
        throw new \Exception('Xendit driver not implemented yet');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // TODO: Implement Xendit signature verification
        return false;
    }
}