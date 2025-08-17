<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PhotoSession;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createQrPayment(PhotoSession $session): Payment;
    
    public function getStatus(Payment $payment): PaymentStatusDTO;
    
    public function handleWebhook(Request $request): PaymentUpdateDTO;
    
    public function verifyWebhookSignature(Request $request): bool;
}