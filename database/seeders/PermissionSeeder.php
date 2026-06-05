<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // === Define All Permissions ===
        $permissions = [

            // Vault Management
            'vault.create',
            'vault.view',
            'vault.edit',
            'vault.delete',

            // Cash In
            'cash-in.request',
            'cash-in.edit',
            'cash-in.delete',
            'cash-in.view',
            'cash-in.verify',
            'cash-in.approve',
            'cash-in.reject',

            // Cash Out
            'cash-out.create',
            'cash-out.view',
            'cash-out.edit',
            'cash-out.delete',
            'cash-out.view',
            'cash-out.verify',
            'cash-out.approve',

            // Reconciliations
            'reconciliation.view',
            'reconciliation.create',
            'reconciliation.reschedule',
            'reconciliation.read',
            'reconciliation.verify',
            'reconciliation.approve',
            'reconciliation.reject',

            // Users & Roles
            'user.create',
            'user.view',
            'user.details',
            'user.edit',
            'user.delete',
            'role.create',
            'role.view',
            'role.edit',
            'role.delete',

            // Permissions
            'permission.create',
            'permission.view',
            'permission.edit',

            // Reports
            'report.view',

            // Settings
            'setting.view',
            'setting.config_audit_view',
            'setting.config_audit_edit',
            'setting.default_view',
            'setting.default_edit',
            'setting.log',
        ];

        $roles = [
            'super-admin',
            'admin',
            'verifier',
            'approver',
            'bag create',
            'custodian',
            'auditor',
            'audit initiator',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdminUser = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('123'),
                'status'   => 'active',
                'verified' => true,
            ]
        );

        $superAdminUser->syncPermissions(Permission::all());

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminUser->assignRole($superAdminRole);
    }
}
