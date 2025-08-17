<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PhotoSession;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MidtransDriver implements PaymentGateway
{
    private string $serverKey;
    private string $clientKey;
    private bool $isSandbox;
    private string $baseUrl;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key');
        $this->clientKey = config('services.midtrans.client_key');
        $this->isSandbox = config('services.midtrans.is_sandbox', true);
        $this->baseUrl = $this->isSandbox 
            ? 'https://api.sandbox.midtrans.com/v2'
            : 'https://api.midtrans.com/v2';
    }

    public function createQrPayment(PhotoSession $session): Payment
    {
        $amount = $this->calculateAmount($session);
        $orderId = 'photobox_' . $session->code . '_' . Str::random(8);
        
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'payment_type' => 'qris',
            'qris' => [
                'acquirer' => 'gopay',
            ],
            'customer_details' => [
                'first_name' => 'PhotoBox',
                'last_name' => 'Customer',
                'email' => 'customer@photobox.app',
            ],
            'item_details' => [
                [
                    'id' => 'photo_print',
                    'price' => Setting::get('payment.price_per_photo', 15000),
                    'quantity' => max(1, $session->photos()->count()),
                    'name' => 'Photo Print',
                ]
            ],
            'custom_expiry' => [
                'expiry_duration' => 15,
                'unit' => 'minute',
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->post($this->baseUrl . '/charge', $payload);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Midtrans payment: ' . $response->body());
        }

        $data = $response->json();

        return Payment::create([
            'session_id' => $session->id,
            'provider' => Payment::PROVIDER_MIDTRANS,
            'provider_txn_id' => $data['transaction_id'],
            'method' => 'qris',
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
            'qr_string' => $data['qr_string'] ?? null,
            'qr_image_url' => $data['qr_code_url'] ?? null,
            'expires_at' => now()->addMinutes(15),
            'payload' => $data,
        ]);
    }

    public function getStatus(Payment $payment): PaymentStatusDTO
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->get($this->baseUrl . '/transaction/status/' . $payment->provider_txn_id);

        if (!$response->successful()) {
            throw new \Exception('Failed to get Midtrans payment status: ' . $response->body());
        }

        $data = $response->json();
        $status = $this->mapMidtransStatus($data['transaction_status'] ?? '');

        return new PaymentStatusDTO(
            status: $status,
            transactionId: $payment->provider_txn_id,
            amount: (int) ($data['gross_amount'] ?? 0),
            paidAt: $status === Payment::STATUS_PAID ? now()->toISOString() : null,
            metadata: $data
        );
    }

    public function handleWebhook(Request $request): PaymentUpdateDTO
    {
        $data = $request->all();
        $status = $this->mapMidtransStatus($data['transaction_status'] ?? '');

        return new PaymentUpdateDTO(
            transactionId: $data['transaction_id'] ?? '',
            status: $status,
            amount: (int) ($data['gross_amount'] ?? 0),
            paidAt: $status === Payment::STATUS_PAID ? now()->toISOString() : null,
            payload: $data
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $data = $request->all();
        $orderId = $data['order_id'] ?? '';
        $statusCode = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';
        
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        $receivedSignature = $data['signature_key'] ?? '';

        return hash_equals($signatureKey, $receivedSignature);
    }

    private function calculateAmount(PhotoSession $session): int
    {
        $pricePerPhoto = Setting::get('payment.price_per_photo', 15000);
        $photoCount = $session->photos()->count();
        
        return $pricePerPhoto * max(1, $photoCount);
    }

    private function mapMidtransStatus(string $midtransStatus): string
    {
        return match ($midtransStatus) {
            'pending' => Payment::STATUS_PENDING,
            'settlement', 'capture' => Payment::STATUS_PAID,
            'expire' => Payment::STATUS_EXPIRED,
            'cancel', 'deny' => Payment::STATUS_FAILED,
            'refund', 'partial_refund' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_FAILED,
        };
    }
}