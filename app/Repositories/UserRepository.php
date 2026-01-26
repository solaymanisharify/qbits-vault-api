<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserRepository
{
    public function __construct(protected RoleService $roleService) {}
    public function index($request = null)
    {
        $query = User::with('roles', 'permissions')->orderBy('created_at', 'desc');;

        // Search functionality
        $search = null;
        if (is_array($request) && isset($request['search'])) {
            $search = $request['search'];
        } elseif (is_object($request) && method_exists($request, 'input')) {
            $search = $request->input('search');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = 15; // Default
        if (is_array($request) && isset($request['per_page'])) {
            $perPage = $request['per_page'];
        } elseif (is_object($request) && method_exists($request, 'input')) {
            $perPage = $request->input('per_page', 15);
        }

        $users = $query->paginate($perPage);

        return $users;
    }

    public function findById(int $id)
    {
        return User::findOrFail($id);
    }

    public function show($id)
    {
        $user = User::with(['roles.permissions', 'permissions'])->findOrFail($id); // Load roles and direct permissions

        $effectivePermissions = $user->getEffectivePermissions();

        return ['data' => $user, 'effective_permissions' => $effectivePermissions];

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Successfully fetch user',
        //     'data' => $user,
        //     'effective_permissions' => $effectivePermissions // Add this
        // ]);
    }
    public function getAllUsersPermissionByName($name)
    {
        return User::permission($name)->get();
    }
    public function create($data)
    {
        // info($data);
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
    }

    public function createUser($request)
    {
        $currentUser = Auth::user();

        $roleData = $request['role'] ?? [];

        if (!is_array($roleData)) {
            $roleData = json_decode($roleData, true) ?? [];
        }

        // Validate that role field exists and is an array
        // if (!isset($request->role) || !is_array($request->role) || empty($request->role)) {
        //     return response()->json([
        //         'error' => 'At least one role must be selected'
        //     ], 422);
        // }

        // Get all role objects from the provided role IDs
        $roles = $this->roleService->find($request['role']);

        // If roleService doesn't have findMultiple, use this instead:
        // $roles = Role::whereIn('id', $request->role)->get();

        if ($roles->isEmpty()) {
            return response()->json([
                'error' => 'Invalid roles provided'
            ], 422);
        }

        // Authorization check for Admin users
        if ($currentUser->hasRole('Admin')) {
            // Check if any of the roles being assigned is Super Admin
            $hasRestrictedRole = $roles->contains(function ($role) {
                return in_array($role->name, ['Super Admin', 'super_admin']);
            });

            if ($hasRestrictedRole) {
                return response()->json([
                    'error' => 'Admins cannot assign Super Admin role'
                ], 403);
            }
        }

        // Create the new user
        $newUser = User::create([
            'name' => $request["name"],
            'email' => $request["email"],
            'password' => bcrypt($request["password"]),
        ]);

        // Assign all roles to the user
        $roleNames = $roles->pluck('name')->toArray();
        $newUser->assignRole($roleNames);

        // Alternative: If using Spatie, you can assign multiple roles at once
        // $newUser->assignRole($roles->pluck('name')->toArray());

        // Load roles relationship for response
        $newUser->load('roles');

        return response()->json([
            'message' => 'User created successfully',
            'user' => $newUser
        ], 201);
    }

    // Add this method to your RoleService if it doesn't exist
    // public function findMultiple(array $roleIds)
    // {
    //     return Role::whereIn('id', $roleIds)->get();
    // }

    public function update($request, $id)
    {
        $user = User::findOrFail($id);

        // Update basic fields if provided
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('status')) {
            $user->status = $request->status;
        }

        $user->save();

        // Handle permission overrides
        if ($request->has('permissions')) {
            // Get all permissions from user's roles
            $rolePermissionIds = $user->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('id')
                ->unique()
                ->toArray();

            // Requested permissions from frontend
            $requestedPermissionIds = $request->permissions;

            // Clear all existing overrides for this user
            DB::table('user_permission_overrides')
                ->where('user_id', $user->id)
                ->delete();

            // Get all available permissions
            $allPermissions = Permission::all();

            foreach ($allPermissions as $permission) {
                $isInRole = in_array($permission->id, $rolePermissionIds);
                $isRequested = in_array($permission->id, $requestedPermissionIds);

                // Add override only if different from role
                if ($isInRole && !$isRequested) {
                    // Permission in role but user shouldn't have it → DENY override
                    DB::table('user_permission_overrides')->insert([
                        'user_id' => $user->id,
                        'permission_id' => $permission->id,
                        'granted' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } elseif (!$isInRole && $isRequested) {
                    // Permission NOT in role but user should have it → GRANT override
                    DB::table('user_permission_overrides')->insert([
                        'user_id' => $user->id,
                        'permission_id' => $permission->id,
                        'granted' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                // If both true or both false → no override needed (role handles it)
            }
        }

        // Return updated user with permissions
        $user->load(['roles.permissions', 'permissions']);

        // Add overrides to response
        $effectivePermissions = $user->getEffectivePermissions();

        return response()->json([
            'message' => 'User updated successfully',
            'data' => array_merge($user->toArray(), [
                'effective_permissions' => $effectivePermissions
            ])
        ]);
    }
}
