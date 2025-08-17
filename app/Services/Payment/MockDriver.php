<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PhotoSession;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockDriver implements PaymentGateway
{
    public function createQrPayment(PhotoSession $session): Payment
    {
        $amount = $this->calculateAmount($session);
        $transactionId = 'mock_' . Str::random(16);
        
        // Create EMV QR string (simplified mock)
        $qrString = $this->generateMockQrString($amount, $transactionId);
        
        return Payment::create([
            'session_id' => $session->id,
            'provider' => Payment::PROVIDER_MOCK,
            'provider_txn_id' => $transactionId,
            'method' => 'qris',
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
            'qr_string' => $qrString,
            'qr_image_url' => null, // In real implementation, this would be generated
            'expires_at' => now()->addMinutes(15),
            'payload' => [
                'mock_data' => true,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }

    public function getStatus(Payment $payment): PaymentStatusDTO
    {
        // Mock: automatically mark as paid after 30 seconds in testing
        if ($payment->isPending() && $payment->created_at->diffInSeconds() > 30) {
            return new PaymentStatusDTO(
                status: Payment::STATUS_PAID,
                transactionId: $payment->provider_txn_id,
                amount: $payment->amount,
                paidAt: now()->toISOString()
            );
        }

        return new PaymentStatusDTO(
            status: $payment->status,
            transactionId: $payment->provider_txn_id,
            amount: $payment->amount
        );
    }

    public function handleWebhook(Request $request): PaymentUpdateDTO
    {
        $data = $request->all();
        
        return new PaymentUpdateDTO(
            transactionId: $data['transaction_id'] ?? '',
            status: $data['status'] ?? Payment::STATUS_FAILED,
            amount: $data['amount'] ?? null,
            paidAt: $data['paid_at'] ?? null,
            payload: $data
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Mock implementation - always return true for testing
        return true;
    }

    private function calculateAmount(PhotoSession $session): int
    {
        $pricePerPhoto = Setting::get('payment.price_per_photo', 15000);
        $photoCount = $session->photos()->count();
        
        return $pricePerPhoto * max(1, $photoCount);
    }

    private function generateMockQrString(int $amount, string $transactionId): string
    {
        // Simplified EMV QR format for testing
        return "00020101021243670016COM.MIDTRANS.WWW01189360050300000898740303UMI51280014ID.CO.QRIS.WWW0215ID{$transactionId}0303UMI5204481253033605405{$amount}5802ID5909PhotoBox6007Jakarta61051234062{$transactionId}6304";
    }
}