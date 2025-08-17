<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PhotoSession;
use App\Models\Setting;
use Illuminate\Http\Request;

class PaymentService
{
    private PaymentGateway $gateway;

    public function __construct()
    {
        $this->gateway = $this->createGateway();
    }

    public function createQrPayment(PhotoSession $session): Payment
    {
        return $this->gateway->createQrPayment($session);
    }

    public function getStatus(Payment $payment): PaymentStatusDTO
    {
        return $this->gateway->getStatus($payment);
    }

    public function handleWebhook(Request $request, string $provider): PaymentUpdateDTO
    {
        $gateway = $this->createGateway($provider);
        
        if (!$gateway->verifyWebhookSignature($request)) {
            throw new \Exception('Invalid webhook signature');
        }

        return $gateway->handleWebhook($request);
    }

    public function updatePaymentFromWebhook(PaymentUpdateDTO $update): ?Payment
    {
        $payment = Payment::where('provider_txn_id', $update->transactionId)->first();
        
        if (!$payment) {
            return null;
        }

        $payment->update([
            'status' => $update->status,
            'paid_at' => $update->status === Payment::STATUS_PAID ? now() : null,
            'payload' => array_merge($payment->payload ?? [], $update->payload ?? []),
        ]);

        // Update session status if payment is completed
        if ($payment->isPaid() && !$payment->session->isPaid()) {
            $payment->session->update([
                'status' => PhotoSession::STATUS_PAID,
                'total_price' => $update->amount ?? $payment->amount,
            ]);
        }

        return $payment->refresh();
    }

    private function createGateway(?string $provider = null): PaymentGateway
    {
        $provider = $provider ?? Setting::get('payment.driver', 'mock');

        return match ($provider) {
            'midtrans' => new MidtransDriver(),
            'xendit' => new XenditDriver(),
            'mock' => new MockDriver(),
            default => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }
}