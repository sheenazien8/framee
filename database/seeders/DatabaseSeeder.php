<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DefaultSettingsSeeder::class,
            SampleBordersSeeder::class,
        ]);

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@photobox.app',
        ]);
        $admin->assignRole('admin');

        // Create operator user
        $operator = User::factory()->create([
            'name' => 'Operator User',
            'email' => 'operator@photobox.app',
        ]);
        $operator->assignRole('operator');
    }
}
