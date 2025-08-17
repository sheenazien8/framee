<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SampleBordersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classicCategory = \App\Models\BorderCategory::where('slug', 'classic')->first();
        $floralCategory = \App\Models\BorderCategory::where('slug', 'floral')->first();

        if (!$classicCategory || !$floralCategory) {
            $this->command->error('Border categories not found. Please run DefaultSettingsSeeder first.');
            return;
        }

        // Create sample borders
        $sampleBorders = [
            [
                'slug' => 'simple-white',
                'name' => 'Simple White Border',
                'category_id' => $classicCategory->id,
                'aspect_ratio' => '4:3',
                'preview_path' => 'borders/simple-white/preview.png',
                'file_path' => 'borders/simple-white/frame.png',
                'manifest' => [
                    'name' => 'Simple White Border',
                    'slug' => 'simple-white',
                    'category' => 'classic',
                    'aspect_ratio' => '4:3',
                    'safe_zone' => [
                        'x' => 50,
                        'y' => 50,
                        'width' => 1180,
                        'height' => 835,
                    ],
                    'author' => 'PhotoBox Team',
                    'license' => 'MIT',
                    'version' => '1.0.0',
                ],
                'is_active' => true,
            ],
            [
                'slug' => 'elegant-black',
                'name' => 'Elegant Black Border',
                'category_id' => $classicCategory->id,
                'aspect_ratio' => '4:3',
                'preview_path' => 'borders/elegant-black/preview.png',
                'file_path' => 'borders/elegant-black/frame.png',
                'manifest' => [
                    'name' => 'Elegant Black Border',
                    'slug' => 'elegant-black',
                    'category' => 'classic',
                    'aspect_ratio' => '4:3',
                    'safe_zone' => [
                        'x' => 60,
                        'y' => 60,
                        'width' => 1160,
                        'height' => 815,
                    ],
                    'author' => 'PhotoBox Team',
                    'license' => 'MIT',
                    'version' => '1.0.0',
                ],
                'is_active' => true,
            ],
            [
                'slug' => 'rose-garden',
                'name' => 'Rose Garden',
                'category_id' => $floralCategory->id,
                'aspect_ratio' => '3:4',
                'preview_path' => 'borders/rose-garden/preview.png',
                'file_path' => 'borders/rose-garden/frame.png',
                'manifest' => [
                    'name' => 'Rose Garden',
                    'slug' => 'rose-garden',
                    'category' => 'floral',
                    'aspect_ratio' => '3:4',
                    'safe_zone' => [
                        'x' => 80,
                        'y' => 100,
                        'width' => 1040,
                        'height' => 1400,
                    ],
                    'author' => 'PhotoBox Team',
                    'license' => 'MIT',
                    'version' => '1.0.0',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($sampleBorders as $borderData) {
            \App\Models\Border::updateOrCreate(
                ['slug' => $borderData['slug']],
                $borderData
            );
        }

        $this->command->info('Sample borders created successfully!');
        $this->command->warn('Note: Actual border image files need to be added manually to storage/app/public/borders/');
    }
}
