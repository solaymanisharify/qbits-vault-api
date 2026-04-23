<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // === Define All Permissions ===
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
            'reconciliation.read',
            'reconciliation.verify',
            'reconciliation.approve',
            'reconciliation.reject',

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

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // === Create Default Super Admin User ===
        $superAdminUser = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('123'), // Change this in production!
            ]
        );

        // Assign ALL permissions directly to the user (User-wise)
        $superAdminUser->syncPermissions(Permission::all());

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminUser->assignRole($superAdminRole);

        // Optional: You can create more users with specific permissions here
        // Example:
        /*
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@example.com'],
            ['name' => 'Cashier User', 'password' => bcrypt('123')]
        );
        $cashier->syncPermissions([
            'cash-in.create',
            'cash-in.view',
            'cash-out.create',
            'cash-out.view',
            'vault.view',
        ]);
        */
    }
}
