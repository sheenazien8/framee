<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'borders.view',
            'borders.create',
            'borders.update',
            'borders.delete',
            'sessions.create',
            'sessions.update',
            'sessions.view',
            'payments.view',
            'payments.refund',
            'settings.update',
            'admin.access',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $operatorRole = Role::create(['name' => 'operator']);
        $operatorRole->givePermissionTo([
            'sessions.create',
            'sessions.update',
            'sessions.view',
            'payments.view',
        ]);

        $guestRole = Role::create(['name' => 'guest']);
        $guestRole->givePermissionTo([
            'sessions.create',
        ]);
    }
}
