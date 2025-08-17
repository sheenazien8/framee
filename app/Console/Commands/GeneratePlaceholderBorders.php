<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePlaceholderBorders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borders:generate-placeholders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate placeholder border images for development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $borders = [
            [
                'slug' => 'simple-white',
                'name' => 'Simple White Border',
                'color' => [255, 255, 255],
                'border_width' => 50,
            ],
            [
                'slug' => 'elegant-black',
                'name' => 'Elegant Black Border',
                'color' => [0, 0, 0],
                'border_width' => 60,
            ],
            [
                'slug' => 'rose-garden',
                'name' => 'Rose Garden',
                'color' => [255, 192, 203],
                'border_width' => 80,
            ],
        ];

        foreach ($borders as $border) {
            $this->generateBorderImages($border);
            $this->info("Generated placeholder images for: {$border['name']}");
        }

        $this->info('All placeholder border images generated successfully!');
    }

    private function generateBorderImages(array $border): void
    {
        $slug = $border['slug'];
        $basePath = storage_path("app/public/borders/{$slug}");
        
        // Ensure directory exists
        if (!file_exists($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Generate preview image (600x450 for 4:3 aspect ratio)
        $this->createBorderImage(600, 450, $border, $basePath . '/preview.png');
        
        // Generate frame image (1200x900 for 4:3 aspect ratio)
        $this->createBorderImage(1200, 900, $border, $basePath . '/frame.png');
    }

    private function createBorderImage(int $width, int $height, array $border, string $filename): void
    {
        // Create image
        $image = imagecreatetruecolor($width, $height);
        
        // Enable alpha blending for transparency
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        // Create transparent background
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        
        // Create border color
        $borderColor = imagecolorallocate(
            $image, 
            $border['color'][0], 
            $border['color'][1], 
            $border['color'][2]
        );
        
        $borderWidth = $border['border_width'];
        
        // Draw border (top, bottom, left, right)
        imagefilledrectangle($image, 0, 0, $width, $borderWidth, $borderColor); // Top
        imagefilledrectangle($image, 0, $height - $borderWidth, $width, $height, $borderColor); // Bottom
        imagefilledrectangle($image, 0, 0, $borderWidth, $height, $borderColor); // Left
        imagefilledrectangle($image, $width - $borderWidth, 0, $width, $height, $borderColor); // Right
        
        // Add some decorative elements for variety
        if ($border['slug'] === 'rose-garden') {
            // Add some pink circles for floral effect
            $pink = imagecolorallocate($image, 255, 182, 193);
            for ($i = 0; $i < 10; $i++) {
                $x = rand($borderWidth, $width - $borderWidth);
                $y = rand($borderWidth, $borderWidth * 2);
                imagefilledellipse($image, $x, $y, 20, 20, $pink);
            }
        } elseif ($border['slug'] === 'elegant-black') {
            // Add some gray accent lines
            $gray = imagecolorallocate($image, 128, 128, 128);
            $lineWidth = 5;
            imagefilledrectangle($image, $borderWidth - $lineWidth, $borderWidth - $lineWidth, 
                               $width - $borderWidth + $lineWidth, $borderWidth, $gray);
            imagefilledrectangle($image, $borderWidth - $lineWidth, $height - $borderWidth, 
                               $width - $borderWidth + $lineWidth, $height - $borderWidth + $lineWidth, $gray);
        }
        
        // Save as PNG
        imagepng($image, $filename);
        
        // Clean up
        imagedestroy($image);
    }
}
