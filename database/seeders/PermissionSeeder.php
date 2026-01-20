<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // === Core Permissions (Your grouped list) ===
        $permissions = [
            // Vault Management
            'vault.create',
            'vault.view',
            'vault.edit',
            'vault.delete',

            // Cash In
            'cash-in.create',
            'cash-in.view',
            'cash-in.verify',
            'cash-in.approve',
            'cash-in.reject',

            // Cash Out
            'cash-out.create',
            'cash-out.view',
            'cash-out.verify',
            'cash-out.approve',

            // Reconciliations
            'reconciliation.view',
            'reconciliation.create',
            'reconciliation.export',

            // Users & Roles
            'user.create',
            'user.view',
            'user.edit',
            'user.delete',
            'role.create',
            'role.view',
            'role.edit',
            'role.delete',
            'permission.create',
            'permission.view',

            // Reports
            'report.daily',
            'report.weekly',
            'report.custom',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // === Create Roles ===
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $verifier = Role::firstOrCreate(['name' => 'Verifier']);
        $cashier = Role::firstOrCreate(['name' => 'Cashier']);

        // === Assign ALL permissions to Super Admin & Admin ===
        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions(Permission::all());

        // === Specific roles ===
        $verifier->syncPermissions([
            'cash-in.view',
            'cash-in.verify',
            'cash-in.approve',
            'cash-in.reject',
            'cash-out.view',
            'vault.view',
            'reconciliation.view',
        ]);

        $cashier->syncPermissions([
            'cash-in.create',
            'cash-in.view',
            'cash-out.create',
            'cash-out.view',
            'vault.view',
        ]);

        // === Create Default Super Admin User ===
        $superAdminUser = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('123'), // Change in production!
            ]
        );

        // Assign role if not already
        if (!$superAdminUser->hasRole('Super Admin')) {
            $superAdminUser->assignRole('Super Admin');
        }
    }
}