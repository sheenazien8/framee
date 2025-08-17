<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Models\Photo;
use App\Models\Border;
use App\Models\Setting;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SessionController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kiosk_label' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $session = PhotoSession::create([
            'status' => PhotoSession::STATUS_IDLE,
            'kiosk_label' => $request->kiosk_label,
            'expires_at' => now()->addMinutes(Setting::get('kiosk.session_expiry', 30)),
        ]);

        return response()->json($session, 201);
    }

    public function show(string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with(['photos', 'payments'])
            ->firstOrFail();

        return response()->json($session);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:idle,capturing,review,checkout,paid,completed,expired',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $session = PhotoSession::where('code', $code)->firstOrFail();
        $session->update(['status' => $request->status]);

        return response()->json($session);
    }

    public function uploadPhoto(Request $request, string $code): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $session = PhotoSession::where('code', $code)->firstOrFail();

        // Store the uploaded photo
        $file = $request->file('photo');
        $filename = $session->code . '_' . uniqid() . '.jpg';
        $path = $file->storeAs('photos/originals', $filename, 'public');

        // Get image dimensions
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->path());

        $photo = Photo::create([
            'session_id' => $session->id,
            'original_path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
            'meta' => [
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now()->toISOString(),
            ],
        ]);

        // Update session status
        $session->update(['status' => PhotoSession::STATUS_REVIEW]);

        return response()->json($photo, 201);
    }

    public function removePhoto(Request $request, string $code, int $photoId): JsonResponse
    {
        $session = PhotoSession::where('code', $code)->firstOrFail();
        $photo = $session->photos()->findOrFail($photoId);

        // Delete files (only if they exist)
        $filesToDelete = array_filter([
            $photo->original_path,
            $photo->processed_path,
        ]);

        if (!empty($filesToDelete)) {
            Storage::disk('public')->delete($filesToDelete);
        }

        $photo->delete();

        return response()->json(['message' => 'Photo removed successfully']);
    }

    public function selectBorder(Request $request, string $code): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'border_id' => 'required|exists:borders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $session = PhotoSession::where('code', $code)->firstOrFail();
        $border = Border::findOrFail($request->border_id);

        // Store border selection in session meta
        $meta = $session->meta ?? [];
        $meta['selected_border_id'] = $border->id;
        
        $session->update([
            'status' => PhotoSession::STATUS_REVIEW,
            'meta' => $meta,
        ]);

        return response()->json([
            'session' => $session,
            'border' => $border,
        ]);
    }

    public function composeImage(Request $request, string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos to compose'], 400);
        }

        $photo = $session->photos->first();

        // Get selected border from request or session
        $border = null;
        if ($request->border_id) {
            $border = Border::find($request->border_id);
        }

        // For development, run composition synchronously to see results immediately
        if (app()->environment('local')) {
            $job = new \App\Jobs\ComposeFinalImage($photo, $border, true);
            $job->handle();

            // Reload photo to get updated processed_path
            $photo->refresh();

            $previewUrl = $photo->processed_path
                ? asset('storage/' . $photo->processed_path)
                : asset('storage/' . $photo->original_path);

            return response()->json([
                'preview_url' => $previewUrl,
                'watermarked' => true,
                'ready_for_checkout' => true,
                'composing' => false,
                'border_applied' => $border ? $border->name : null,
            ]);
        } else {
            // In production, use queue
            \App\Jobs\ComposeFinalImage::dispatch($photo, $border, true);

            return response()->json([
                'preview_url' => asset('storage/' . $photo->original_path),
                'watermarked' => true,
                'ready_for_checkout' => true,
                'composing' => true,
            ]);
        }
    }

    public function composeAllPhotos(Request $request, string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos to compose'], 400);
        }

        // Get selected border from request or session meta
        $border = null;
        $borderId = $request->border_id ?? $session->meta['selected_border_id'] ?? null;
        
        if ($borderId) {
            $border = Border::find($borderId);
        }

        \Log::info('Composing all photos', [
            'session_code' => $code,
            'border_id' => $borderId,
            'border_name' => $border?->name,
            'photo_count' => $session->photos->count(),
        ]);

        $processedPhotos = [];

        foreach ($session->photos as $photo) {
            try {
                if (app()->environment('local')) {
                    // For development, run composition synchronously
                    $job = new \App\Jobs\ComposeFinalImage($photo, $border, true);
                    $job->handle();
                    $photo->refresh();
                } else {
                    // In production, use queue
                    \App\Jobs\ComposeFinalImage::dispatch($photo, $border, true);
                }

                $processedPhotos[] = [
                    'id' => $photo->id,
                    'original_url' => asset('storage/' . $photo->original_path),
                    'preview_url' => $photo->processed_path
                        ? asset('storage/' . $photo->processed_path)
                        : asset('storage/' . $photo->original_path),
                    'processed' => !!$photo->processed_path,
                ];
            } catch (\Exception $e) {
                \Log::error('Failed to compose photo', [
                    'photo_id' => $photo->id,
                    'border_id' => $border?->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'processed_photos' => $processedPhotos,
            'total_photos' => count($processedPhotos),
            'border_applied' => $border ? $border->name : null,
        ]);
    }

    public function checkout(Request $request, string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos to checkout'], 400);
        }

        // Create payment
        $payment = $this->paymentService->createQrPayment($session);

        // Update session status
        $session->update(['status' => PhotoSession::STATUS_CHECKOUT]);

        return response()->json([
            'payment' => $payment,
            'qr_image_url' => $payment->qr_image_url,
            'qr_string' => $payment->qr_string,
            'amount' => $payment->amount,
            'expires_at' => $payment->expires_at,
        ]);
    }

    public function status(string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with(['latestPayment'])
            ->firstOrFail();

        $paymentStatus = 'idle';
        if ($session->latestPayment) {
            $paymentStatus = $session->latestPayment->status;

            // Check for updated status from payment provider
            try {
                $statusUpdate = $this->paymentService->getStatus($session->latestPayment);
                if ($statusUpdate->status !== $session->latestPayment->status) {
                    $session->latestPayment->update(['status' => $statusUpdate->status]);
                    $paymentStatus = $statusUpdate->status;

                    // Update session if payment completed
                    if ($statusUpdate->status === 'paid' && !$session->isPaid()) {
                        $session->update(['status' => PhotoSession::STATUS_PAID]);
                    }
                }
            } catch (\Exception $e) {
                // Ignore payment status check errors
            }
        }

        return response()->json([
            'session' => $session,
            'payment_status' => $paymentStatus,
        ]);
    }

    public function regeneratePayment(Request $request, string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)->firstOrFail();

        // Mark existing payments as expired
        $session->payments()->where('status', 'pending')->update(['status' => 'expired']);

        // Create new payment
        $payment = $this->paymentService->createQrPayment($session);

        return response()->json([
            'payment' => $payment,
            'qr_image_url' => $payment->qr_image_url,
            'qr_string' => $payment->qr_string,
            'amount' => $payment->amount,
            'expires_at' => $payment->expires_at,
        ]);
    }

    public function download(string $code)
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if (!$session->isPaid()) {
            return response()->json(['error' => 'Payment required'], 402);
        }

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos available'], 404);
        }

        // Check if this is a request for download URLs (for print functionality)
        if (request()->wantsJson() || request()->expectsJson()) {
            $downloadUrls = [];
            
            foreach ($session->photos as $index => $photo) {
                $filePath = $photo->processed_path ?: $photo->original_path;
                
                if (Storage::disk('public')->exists($filePath)) {
                    $publicUrl = Storage::disk('public')->url($filePath);
                    $downloadUrls[] = [
                        'photo_id' => $photo->id,
                        'download_url' => $publicUrl,
                        'filename' => $session->code . '_photo_' . ($index + 1) . '.jpg',
                    ];
                }
            }

            return response()->json([
                'photos' => $downloadUrls,
                'total_photos' => count($downloadUrls),
                'expires_at' => now()->addMinutes(30),
            ]);
        }

        // For direct file download, download the first photo (backward compatibility)
        $photo = $session->photos->first();
        $filePath = $photo->processed_path ?: $photo->original_path;

        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $filename = $session->code . '_photo.jpg';
        return Storage::disk('public')->download($filePath, $filename);
    }

    public function printPhoto(string $code): JsonResponse
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if (!$session->isPaid()) {
            return response()->json(['error' => 'Payment required'], 402);
        }

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos available'], 404);
        }

        $downloadUrls = [];
        
        foreach ($session->photos as $index => $photo) {
            $filePath = $photo->processed_path ?: $photo->original_path;
            
            if (Storage::disk('public')->exists($filePath)) {
                $publicUrl = Storage::disk('public')->url($filePath);
                $downloadUrls[] = [
                    'photo_id' => $photo->id,
                    'download_url' => $publicUrl,
                    'filename' => $session->code . '_photo_' . ($index + 1) . '.jpg',
                ];
            }
        }

        return response()->json([
            'photos' => $downloadUrls,
            'total_photos' => count($downloadUrls),
            'session_code' => $session->code,
        ]);
    }

    public function downloadQrCode(string $code)
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if (!$session->isPaid() && app()->environment('production')) {
            return response()->json(['error' => 'Payment required'], 402);
        }

        if ($session->photos->isEmpty()) {
            return response()->json(['error' => 'No photos available'], 404);
        }

        // Generate the download URL
        $downloadUrl = route('api.v1.sessions.download.direct', ['code' => $code]);

        // Generate QR code as SVG
        $qrCode = QrCode::size(300)
            ->style('round')
            ->margin(2)
            ->generate($downloadUrl);

        return response($qrCode)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function downloadDirect(string $code)
    {
        $session = PhotoSession::where('code', $code)
            ->with('photos')
            ->firstOrFail();

        if (!$session->isPaid()) {
            abort(402, 'Payment required');
        }

        if ($session->photos->isEmpty()) {
            abort(404, 'No photos available');
        }

        // If specific photo ID is requested
        $photoId = request()->query('photo_id');
        if ($photoId) {
            $photo = $session->photos->where('id', $photoId)->first();
            if (!$photo) {
                abort(404, 'Photo not found');
            }
        } else {
            // Default to first photo for backward compatibility
            $photo = $session->photos->first();
        }

        $filePath = $photo->processed_path ?: $photo->original_path;

        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        $photoIndex = $session->photos->search(function($p) use ($photo) {
            return $p->id === $photo->id;
        }) + 1;
        
        $filename = $session->code . '_photo_' . $photoIndex . '.jpg';
        return Storage::disk('public')->download($filePath, $filename);
    }
}
