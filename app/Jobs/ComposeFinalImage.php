<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\Border;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ComposeFinalImage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Photo $photo,
        public ?Border $border = null,
        public bool $addWatermark = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $manager = new ImageManager(new Driver());
            
            // Load the original photo
            $originalPath = Storage::disk('public')->path($this->photo->original_path);
            $image = $manager->read($originalPath);

            // Get target dimensions from border or use photo dimensions
            if ($this->border) {
                $aspectRatio = $this->border->aspect_ratio;
                [$targetWidth, $targetHeight] = explode(':', $aspectRatio);
                $targetRatio = $targetWidth / $targetHeight;
                
                // Calculate crop dimensions
                $sourceRatio = $image->width() / $image->height();
                
                if ($sourceRatio > $targetRatio) {
                    // Source is wider, crop horizontally
                    $cropHeight = $image->height();
                    $cropWidth = $cropHeight * $targetRatio;
                    $cropX = ($image->width() - $cropWidth) / 2;
                    $cropY = 0;
                } else {
                    // Source is taller, crop vertically
                    $cropWidth = $image->width();
                    $cropHeight = $cropWidth / $targetRatio;
                    $cropX = 0;
                    $cropY = ($image->height() - $cropHeight) / 2;
                }
                
                // Crop to aspect ratio
                $image->crop($cropWidth, $cropHeight, $cropX, $cropY);
                
                // Resize to standard dimensions (maintaining aspect ratio)
                $maxWidth = Setting::get('image.max_width', 2400);
                $maxHeight = Setting::get('image.max_height', 3200);
                
                if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                    $image->scaleDown($maxWidth, $maxHeight);
                }
            }

            // Add watermark if requested (before payment)
            if ($this->addWatermark) {
                $this->addWatermark($image);
            }

            // Apply border if specified
            if ($this->border) {
                $this->applyBorder($image);
            }

            // Generate filename
            $filename = $this->photo->session->code . '_' . ($this->border ? $this->border->slug : 'no-border') . '_final.jpg';
            $processedPath = 'photos/processed/' . $filename;

            // Save the processed image
            $quality = Setting::get('image.jpeg_quality', 90);
            $encodedImage = $image->toJpeg($quality);
            Storage::disk('public')->put($processedPath, $encodedImage);

            // Update photo record
            $this->photo->update([
                'processed_path' => $processedPath,
                'width' => $image->width(),
                'height' => $image->height(),
                'meta' => array_merge($this->photo->meta ?? [], [
                    'processed_at' => now()->toISOString(),
                    'border_applied' => $this->border?->slug,
                    'watermarked' => $this->addWatermark,
                ]),
            ]);

        } catch (\Exception $e) {
            \Log::error('Image composition failed', [
                'photo_id' => $this->photo->id,
                'border_id' => $this->border?->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    private function addWatermark($image): void
    {
        // For now, just add a simple text overlay using basic drawing
        // In Intervention Image v3, watermarking is simplified
        $watermarkText = 'PAY TO UNLOCK';
        
        try {
            // Use basic text drawing (this is a simplified approach)
            $image->text($watermarkText, $image->width() / 2, $image->height() / 2, function ($font) {
                $font->size(48);
                $font->color('rgba(255, 255, 255, 0.8)');
                $font->align('center');
                $font->valign('middle');
            });
        } catch (\Exception $e) {
            // If text drawing fails, add a simple rectangle overlay
            \Log::warning('Text watermarking failed, using rectangle overlay', ['error' => $e->getMessage()]);
            
            // Create a semi-transparent overlay using GD directly
            $canvas = $image->core()->native();
            
            // Create overlay color
            $overlayColor = imagecolorallocatealpha($canvas, 0, 0, 0, 90); // Semi-transparent black
            
            // Add diagonal stripes as watermark
            for ($i = 0; $i < $image->width() + $image->height(); $i += 100) {
                imageline($canvas, $i, 0, $i - $image->height(), $image->height(), $overlayColor);
            }
        }
    }

    private function applyBorder($image): void
    {
        if (!$this->border) return;

        // Load border frame
        $borderPath = Storage::disk('public')->path($this->border->file_path);
        if (!file_exists($borderPath)) {
            \Log::warning('Border file not found: ' . $borderPath);
            return;
        }

        $manager = new ImageManager(new Driver());
        $borderImage = $manager->read($borderPath);

        // Resize border to match image dimensions
        $borderImage->resize($image->width(), $image->height());

        // Apply border overlay
        $image->place($borderImage, 'top-left', 0, 0);
    }
}
