<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Roles
        $superAdminRole = Role::create(['name' => 'super_admin']);
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        // Permissions
        Permission::create(['name' => 'create-admin']);
        Permission::create(['name' => 'create-user']);
        Permission::create(['name' => 'assign-role']);
        Permission::create(['name' => 'assign-permission']);
        Permission::create(['name' => 'view-users']);

        // Assign to roles
        $superAdminRole->givePermissionTo(Permission::all());
        $adminRole->givePermissionTo(['create-user', 'assign-role', 'assign-permission', 'view-users']);
        $userRole->givePermissionTo(['view-users']);

        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('123'),
            'role' => 'super_admin',
        ]);

        $superAdmin->assignRole('super_admin');
    }
}
