<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Payment settings
        \App\Models\Setting::set('payment.price_per_photo', 15000, 'payment');
        \App\Models\Setting::set('payment.driver', 'mock', 'payment');
        \App\Models\Setting::set('payment.currency', 'IDR', 'payment');

        // Kiosk settings
        \App\Models\Setting::set('kiosk.idle_timeout', 300, 'kiosk'); // 5 minutes
        \App\Models\Setting::set('kiosk.session_expiry', 1800, 'kiosk'); // 30 minutes
        \App\Models\Setting::set('kiosk.label', 'PhotoBox Kiosk #1', 'kiosk');

        // Image settings
        \App\Models\Setting::set('image.max_width', 2400, 'image');
        \App\Models\Setting::set('image.max_height', 3200, 'image');
        \App\Models\Setting::set('image.jpeg_quality', 90, 'image');

        // Create default border categories
        \App\Models\BorderCategory::create(['name' => 'Classic', 'slug' => 'classic']);
        \App\Models\BorderCategory::create(['name' => 'Floral', 'slug' => 'floral']);
        \App\Models\BorderCategory::create(['name' => 'Holiday', 'slug' => 'holiday']);
        \App\Models\BorderCategory::create(['name' => 'Wedding', 'slug' => 'wedding']);
        \App\Models\BorderCategory::create(['name' => 'Birthday', 'slug' => 'birthday']);
    }
}
