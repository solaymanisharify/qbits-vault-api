<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\LogService;
use App\Services\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class UserRepository
{
    public function __construct(protected RoleService $roleService, protected LogService $logService) {}

    public function index($request = null)
    {
        $authUser = auth()->user();
        $isSuperAdmin = $authUser->hasRole('super-admin');
        $isAdmin      = $authUser->hasRole('admin');

        // Non-admin, non-superadmin → return only their own data
        if (!$isSuperAdmin && !$isAdmin) {
            return User::with('roles', 'permissions', 'vaultAssignments.vault:id,name', 'defaultVault:id,name')
                ->where('id', $authUser->id)
                ->paginate(1);
        }

        $query = User::with('roles', 'permissions', 'vaultAssignments.vault:id,name', 'defaultVault:id,name')
            ->where('status', '!=', 'archived')
            ->orderBy('created_at', 'desc');

        // Admin → exclude superadmin users
        if ($isAdmin && !$isSuperAdmin) {
            $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'super-admin'));
        }

        // Search
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
        $perPage = 15;
        if (is_array($request) && isset($request['per_page'])) {
            $perPage = $request['per_page'];
        } elseif (is_object($request) && method_exists($request, 'input')) {
            $perPage = $request->input('per_page', 15);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id)
    {
        return User::findOrFail($id);
    }
    public function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    public function show($id)
    {
        $user = User::with(['roles.permissions', 'permissions', 'vaultAssignments.vault:id,name,bag_balance_limit,bag_min_bal_limit'])->findOrFail($id);

        $effectivePermissions = $user->getEffectivePermissions();

        return ['data' => $user, 'effective_permissions' => $effectivePermissions];
    }
    public function getAllUsersPermissionByName($name)
    {
        return User::permission($name)->get();
    }
    public function create($data)
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'status' => 'inactive'
        ]);
    }

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
            return errorResponse('Invalid roles provided', [], 422);
        }

        // Authorization check
        if ($currentUser->hasRole('Admin')) {
            $hasRestrictedRole = $roles->contains(
                fn($role) =>
                in_array($role->name, ['Super Admin', 'super_admin', 'super-admin'])
            );

            if ($hasRestrictedRole) {
                return errorResponse('Admins cannot assign Super Admin role', [], 403);
            }
        }

        $imagePath = null;

        // Support both Request object and Array input
        if (is_object($request) && method_exists($request, 'hasFile')) {
            if ($request->hasFile('profile_image')) {
                $imagePath = $request->file('profile_image')->store('users/profile', 'public');
            } elseif ($request->hasFile('avatar')) {
                $imagePath = $request->file('avatar')->store('users/profile', 'public');
            }
        } elseif (is_array($request)) {
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

        $this->logService->activityLog(
            'created',
            'user',
            "User {$newUser->name} ({$newUser->email}) with roles: " . implode(', ', $roleNames),
            [
                'name'   => $newUser->name,
                'email'  => $newUser->email,
                'status' => $newUser->status,
                'assigned_roles' => $roleNames,
                'assigned_by'    => Auth::user()?->email ?? 'System'
            ]
        );


        return successResponse("User created successfully", $newUser, 201);
    }

    public function update($request, $id)
    {

        $user = User::findOrFail($id);

        // If $request is an array, convert to object or use data_get
        $data = is_array($request) ? $request : $request->all();

        // 1. Update basic fields
        if (isset($data['name']))   $user->name = $data['name'];
        if (isset($data['email']))  $user->email = $data['email'];
        if (isset($data['status'])) $user->status = $data['status'];
        if (isset($data['default_vault_id'])) $user->default_vault_id = $data['default_vault_id'];


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


            $allPermissions = Permission::all();

            foreach ($allPermissions as $permission) {
                $isInRole = in_array($permission->id, $rolePermissionIds);
                $isRequested = in_array($permission->id, $requestedPermissionIds);

                if ($isInRole && !$isRequested) {
                   
                } elseif (!$isInRole && $isRequested) {
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

    public function checkUserPhoneNumberExistenceByUserId($phone, $userId)
    {
        return User::where('phone', $phone)
            ->where('id', '!=', $userId)
            ->exists();
    }
    public function getAllActiveUsersWithoutSpecificId($userId)
    {
        return User::where('id', '!=', $userId)
            ->where('status', 'active')
            ->where('verified', true)
            ->select('id', 'name', 'email')
            ->get();
    }
}
