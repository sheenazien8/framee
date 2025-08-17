<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function midtransWebhook(Request $request): Response
    {
        try {
            $update = $this->paymentService->handleWebhook($request, 'midtrans');
            $payment = $this->paymentService->updatePaymentFromWebhook($update);

            if (!$payment) {
                return response('Payment not found', 404);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            \Log::error('Midtrans webhook error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);
            
            return response('Error processing webhook', 500);
        }
    }

    public function xenditWebhook(Request $request): Response
    {
        try {
            $update = $this->paymentService->handleWebhook($request, 'xendit');
            $payment = $this->paymentService->updatePaymentFromWebhook($update);

            if (!$payment) {
                return response('Payment not found', 404);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            \Log::error('Xendit webhook error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);
            
            return response('Error processing webhook', 500);
        }
    }
}
