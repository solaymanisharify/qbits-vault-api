<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class UserRepository
{
    public function __construct(protected RoleService $roleService) {}
    public function index($request = null)
    {
        $query = User::with('roles', 'permissions', 'vaultAssignments')->orderBy('created_at', 'desc');;

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
        $user = User::with(['roles.permissions', 'permissions', 'vaultAssignments'])->findOrFail($id); // Load roles and direct permissions

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

    // public function createUser($request)
    // {
    //     $currentUser = Auth::user();

    //     $roleData = $request['role'] ?? [];

    //     if (!is_array($roleData)) {
    //         $roleData = json_decode($roleData, true) ?? [];
    //     }

    //     // Validate that role field exists and is an array
    //     // if (!isset($request->role) || !is_array($request->role) || empty($request->role)) {
    //     //     return response()->json([
    //     //         'error' => 'At least one role must be selected'
    //     //     ], 422);
    //     // }

    //     // Get all role objects from the provided role IDs
    //     $roles = $this->roleService->find($request['role']);

    //     // If roleService doesn't have findMultiple, use this instead:
    //     // $roles = Role::whereIn('id', $request->role)->get();

    //     if ($roles->isEmpty()) {
    //         return response()->json([
    //             'error' => 'Invalid roles provided'
    //         ], 422);
    //     }

    //     // Authorization check for Admin users
    //     if ($currentUser->hasRole('Admin')) {
    //         // Check if any of the roles being assigned is Super Admin
    //         $hasRestrictedRole = $roles->contains(function ($role) {
    //             return in_array($role->name, ['Super Admin', 'super_admin']);
    //         });

    //         if ($hasRestrictedRole) {
    //             return response()->json([
    //                 'error' => 'Admins cannot assign Super Admin role'
    //             ], 403);
    //         }
    //     }

    //     // Create the new user
    //     $newUser = User::create([
    //         'name' => $request["name"],
    //         'email' => $request["email"],
    //         'password' => bcrypt($request["password"]),
    //     ]);

    //     // Assign all roles to the user
    //     $roleNames = $roles->pluck('name')->toArray();
    //     $newUser->assignRole($roleNames);

    //     // Alternative: If using Spatie, you can assign multiple roles at once
    //     // $newUser->assignRole($roles->pluck('name')->toArray());

    //     // Load roles relationship for response
    //     $newUser->load('roles');

    //     return response()->json([
    //         'message' => 'User created successfully',
    //         'user' => $newUser
    //     ], 201);
    // }
    public function createUser($request)
    {
        $currentUser = Auth::user();

        // Handle roles
        $roleData = $request['role'] ?? [];
        if (!is_array($roleData)) {
            $roleData = json_decode($roleData, true) ?? [];
        }

        $roles = $this->roleService->find($roleData);

        if ($roles->isEmpty()) {
            return response()->json(['error' => 'Invalid roles provided'], 422);
        }

        // Authorization check
        if ($currentUser->hasRole('Admin')) {
            $hasRestrictedRole = $roles->contains(
                fn($role) =>
                in_array($role->name, ['Super Admin', 'super_admin'])
            );

            if ($hasRestrictedRole) {
                return response()->json([
                    'error' => 'Admins cannot assign Super Admin role'
                ], 403);
            }
        }

        // ====================== FIXED IMAGE HANDLING ======================
        $imagePath = null;

        // Support both Request object and Array input
        if (is_object($request) && method_exists($request, 'hasFile')) {
            // $request is Illuminate\Http\Request
            if ($request->hasFile('profile_image')) {
                $imagePath = $request->file('profile_image')->store('users/profile', 'public');
            } elseif ($request->hasFile('avatar')) {
                $imagePath = $request->file('avatar')->store('users/profile', 'public');
            }
        } elseif (is_array($request)) {
            // $request is array (most likely from $request->all() or validated data)
            // Files are usually not present in array, but we keep the check for future
            // If you're sending file via FormData, controller should pass full $request
        }

        // Create User
        $newUser = User::create([
            'name'     => $request['name'] ?? null,
            'email'    => $request['email'] ?? null,
            'password' => bcrypt($request['password'] ?? ''),
            'img'      => $imagePath,
            'status'   => $request['status'] ?? 'active',
        ]);

        // Assign Roles
        $roleNames = $roles->pluck('name')->toArray();
        $newUser->assignRole($roleNames);

        $newUser->load('roles');

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $newUser
        ], 201);
    }
    // Add this method to your RoleService if it doesn't exist
    // public function findMultiple(array $roleIds)
    // {
    //     return Role::whereIn('id', $roleIds)->get();
    // }

    // public function update($request, $id)
    // {
    //     $user = User::findOrFail($id);

    //     // Update basic fields if provided
    //     if ($request->has('name')) {
    //         $user->name = $request->name;
    //     }
    //     if ($request->has('email')) {
    //         $user->email = $request->email;
    //     }
    //     if ($request->has('status')) {
    //         $user->status = $request->status;
    //     }

    //     $user->save();

    //     // Handle permission overrides
    //     if ($request->has('permissions')) {
    //         // Get all permissions from user's roles
    //         $rolePermissionIds = $user->roles()
    //             ->with('permissions')
    //             ->get()
    //             ->pluck('permissions')
    //             ->flatten()
    //             ->pluck('id')
    //             ->unique()
    //             ->toArray();

    //         // Requested permissions from frontend
    //         $requestedPermissionIds = $request->permissions;

    //         // Clear all existing overrides for this user
    //         DB::table('user_permission_overrides')
    //             ->where('user_id', $user->id)
    //             ->delete();

    //         // Get all available permissions
    //         $allPermissions = Permission::all();

    //         foreach ($allPermissions as $permission) {
    //             $isInRole = in_array($permission->id, $rolePermissionIds);
    //             $isRequested = in_array($permission->id, $requestedPermissionIds);

    //             // Add override only if different from role
    //             if ($isInRole && !$isRequested) {
    //                 // Permission in role but user shouldn't have it → DENY override
    //                 DB::table('user_permission_overrides')->insert([
    //                     'user_id' => $user->id,
    //                     'permission_id' => $permission->id,
    //                     'granted' => false,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             } elseif (!$isInRole && $isRequested) {
    //                 // Permission NOT in role but user should have it → GRANT override
    //                 DB::table('user_permission_overrides')->insert([
    //                     'user_id' => $user->id,
    //                     'permission_id' => $permission->id,
    //                     'granted' => true,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //             // If both true or both false → no override needed (role handles it)
    //         }
    //     }

    //     // Return updated user with permissions
    //     $user->load(['roles.permissions', 'permissions']);

    //     // Add overrides to response
    //     $effectivePermissions = $user->getEffectivePermissions();

    //     return response()->json([
    //         'message' => 'User updated successfully',
    //         'data' => array_merge($user->toArray(), [
    //             'effective_permissions' => $effectivePermissions
    //         ])
    //     ]);
    // }

    public function update($request, $id)
    {

        $user = User::findOrFail($id);

        // If $request is an array, convert to object or use data_get
        $data = is_array($request) ? $request : $request->all();

        // 1. Update basic fields
        if (isset($data['name']))   $user->name = $data['name'];
        if (isset($data['email']))  $user->email = $data['email'];
        if (isset($data['status'])) $user->status = $data['status'];

        // 2. Handle Current Address (Nested Data)
        // if (isset($data['current'])) {
        //     $current = $data['current'];
        //     $user->current_address  = $current['street']   ?? $user->current_address;
        //     $user->current_division = $current['division'] ?? $user->current_division;
        //     $user->current_district = $current['district'] ?? $user->current_district;
        //     $user->current_thana    = $current['upazila']  ?? $user->current_thana;
        // }

        // // 3. Handle Permanent Address (Nested Data)
        // if (isset($data['permanent'])) {
        //     $permanent = $data['permanent'];
        //     $user->permanent_address  = $permanent['street']   ?? $user->permanent_address;
        //     $user->permanent_division = $permanent['division'] ?? $user->permanent_division;
        //     $user->permanent_district = $permanent['district'] ?? $user->permanent_district;
        //     $user->permanent_thana    = $permanent['upazila']  ?? $user->permanent_thana;
        // }

        // 4. Handle Profile Image Update (Only if $request is the Request object)
        // if (!is_array($request)) {
        //     if ($request->hasFile('img') || $request->hasFile('avatar')) {
        //         $image = $request->file('img') ?? $request->file('avatar');
        //         if ($user->img) {
        //             Storage::disk('public')->delete($user->img);
        //         }
        //         $user->img = $image->store('users/profile', 'public');
        //     }
        // }
        // 4. Handle Image Updates (Profile & KYC)

        // info("Handling file uploads for user update...");
        // Mapping of field name to database column
        $fileFields = [
            'img'           => 'img',           // Profile Image
            'avatar'        => 'img',           // Profile Image Alias
            'nid_front_img' => 'nid_front_img', // NID Front
            'nid_back_img'  => 'nid_back_img',  // NID Back
        ];

        foreach ($fileFields as $inputName => $dbColumn) {
            // Check both the Request object and the $data array for the file
            $file = null;

            if (!is_array($request) && $request->hasFile($inputName)) {
                $file = $request->file($inputName);
            } elseif (isset($data[$inputName]) && $data[$inputName] instanceof \Illuminate\Http\UploadedFile) {
                $file = $data[$inputName];
            }

            if ($file) {
                // Delete old file if exists
                if ($user->{$dbColumn}) {
                    Storage::disk('public')->delete($user->{$dbColumn});
                }

                // Store and update path
                $user->{$dbColumn} = $file->store('users/documents', 'public');
            }
        }


        $user->save();

        // 5. Handle Permission Overrides
        if (isset($data['permissions'])) {
            $rolePermissionIds = $user->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('id')
                ->unique()
                ->toArray();

            $requestedPermissionIds = $data['permissions'];

            DB::table('user_permission_overrides')
                ->where('user_id', $user->id)
                ->delete();

            $allPermissions = Permission::all();

            foreach ($allPermissions as $permission) {
                $isInRole = in_array($permission->id, $rolePermissionIds);
                $isRequested = in_array($permission->id, $requestedPermissionIds);

                if ($isInRole && !$isRequested) {
                    DB::table('user_permission_overrides')->insert([
                        'user_id'      => $user->id,
                        'permission_id' => $permission->id,
                        'granted'      => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                } elseif (!$isInRole && $isRequested) {
                    DB::table('user_permission_overrides')->insert([
                        'user_id'      => $user->id,
                        'permission_id' => $permission->id,
                        'granted'      => true,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }

        $user->load(['roles.permissions', 'permissions']);

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => array_merge($user->toArray(), [
                'effective_permissions' => $user->getEffectivePermissions()
            ])
        ]);
    }

    public function userVerifcation($request, $id)
    {
        $user = User::findOrFail($id);
        $data = is_array($request) ? $request : $request->all();

        // 1. Handle Addresses (Always update if present)
        if (isset($data['current'])) {
            $current = $data['current'];
            $user->current_address  = $current['street']   ?? $user->current_address;
            $user->current_division = $current['division'] ?? $user->current_division;
            $user->current_district = $current['district'] ?? $user->current_district;
            $user->current_thana    = $current['upazila']  ?? $user->current_thana;
        }

        if (isset($data['permanent'])) {
            $permanent = $data['permanent'];
            $user->permanent_address  = $permanent['street']   ?? $user->permanent_address;
            $user->permanent_division = $permanent['division'] ?? $user->permanent_division;
            $user->permanent_district = $permanent['district'] ?? $user->permanent_district;
            $user->permanent_thana    = $permanent['upazila']  ?? $user->permanent_thana;
        }

        // We check if is_skip is true or "1" (since FormData sends strings)
        if (isset($data['is_skip']) && ($data['is_skip'] == true || $data['is_skip'] == "1")) {
            $user->verified = true;
            $user->status = 'active';
            $user->save();

            return $user;
        }

        // 3. Handle File Uploads (Only happens if NOT skipped)
        $fileFields = [
            'img'           => 'img',
            'avatar'        => 'img',
            'nid_front_img' => 'nid_front_img',
            'nid_back_img'  => 'nid_back_img',
        ];

        $hasUploaded = false;
        foreach ($fileFields as $inputName => $dbColumn) {
            $file = null;

            if (!is_array($request) && $request->hasFile($inputName)) {
                $file = $request->file($inputName);
            } elseif (isset($data[$inputName]) && $data[$inputName] instanceof \Illuminate\Http\UploadedFile) {
                $file = $data[$inputName];
            }

            if ($file) {
                if ($user->{$dbColumn}) {
                    Storage::disk('public')->delete($user->{$dbColumn});
                }
                $user->{$dbColumn} = $file->store('users/documents', 'public');
                $hasUploaded = true;
            }
        }

        // 4. Update KYC specific info (Only if NOT skipped)
        if ($hasUploaded || isset($data['kyc_verified_at'])) {
            $user->kyc_verified_at = now();
        }

        $user->status = 'active';
        $user->verified = true;

        $user->save();

        return $user;
    }
}
