<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestImageComposition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:image-composition';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test image composition with borders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Create a test session
        $session = \App\Models\PhotoSession::create([
            'status' => 'review',
            'kiosk_label' => 'Test Kiosk',
            'expires_at' => now()->addMinutes(30),
        ]);

        // Create a simple test image
        $this->createTestImage($session);

        // Get a border to test with
        $border = \App\Models\Border::first();
        
        if (!$border) {
            $this->error('No borders found. Please run the sample borders seeder first.');
            return;
        }

        $this->info("Testing composition with border: {$border->name}");

        // Get the photo
        $photo = $session->photos()->first();
        
        if (!$photo) {
            $this->error('No test photo created.');
            return;
        }

        // Test composition job
        $job = new \App\Jobs\ComposeFinalImage($photo, $border, true);
        $job->handle();

        // Check result
        $photo->refresh();
        
        if ($photo->processed_path) {
            $this->info("✅ Composition successful!");
            $this->info("Original: {$photo->original_path}");
            $this->info("Processed: {$photo->processed_path}");
            $this->info("File exists: " . (file_exists(storage_path('app/public/' . $photo->processed_path)) ? 'Yes' : 'No'));
            $this->info("URL: " . asset('storage/' . $photo->processed_path));
        } else {
            $this->error("❌ Composition failed - no processed_path set");
        }

        // Clean up
        $session->delete();
    }

    private function createTestImage(\App\Models\PhotoSession $session): void
    {
        // Create a simple test image using GD
        $width = 800;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);
        
        // Create a gradient background
        for ($y = 0; $y < $height; $y++) {
            $r = (int)(255 * ($y / $height));
            $g = (int)(100 + 155 * ($y / $height));
            $b = (int)(255 - 255 * ($y / $height));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
        
        // Add some text
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, $width/2 - 50, $height/2 - 10, 'TEST PHOTO', $textColor);
        
        // Save to storage
        $filename = 'test_' . $session->code . '.jpg';
        $path = 'photos/originals/' . $filename;
        $fullPath = storage_path('app/public/' . $path);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        imagejpeg($image, $fullPath, 90);
        imagedestroy($image);
        
        // Create photo record
        \App\Models\Photo::create([
            'session_id' => $session->id,
            'original_path' => $path,
            'width' => $width,
            'height' => $height,
            'meta' => [
                'test_image' => true,
                'created_at' => now()->toISOString(),
            ],
        ]);
        
        $this->info("Created test image: {$path}");
    }
}
