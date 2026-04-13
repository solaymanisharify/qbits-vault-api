<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\VaultAssign;
use App\Services\PermissionService;
use App\Services\UserService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected PermissionService $permissionService
    ) {}

    public function index(Request $request)
    {
        return $this->userService->index($request->all());
    }
    public function show($id)
    {
        return $this->userService->show($id);
    }

    public function create(Request $request)
    {
        return $this->userService->createUser($request->all());
    }

    public function assignRole(Request $request, $userId)
    {
        $authUser = Auth::user();
        $targetUser = User::findOrFail($userId);

        if (!$authUser->can('assign-role')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $targetUser->syncRoles($request->role);

        return response()->json(['message' => 'Role assigned']);
    }

    public function assignPermission(Request $request, $userId)
    {
        $authUser = Auth::user();
        $targetUser = User::findOrFail($userId);

        if (!$authUser->can('assign-permission')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $targetUser->syncPermissions($request->permissions);

        return response()->json(['message' => 'Permissions assigned']);
    }

    public function UpdatePermission(PermissionRequest $request, $userId)
    {
        $this->permissionService->UpdatePermission($request->permissions, $userId);

        return successResponse('Permissions updated successfully', null, 200);
    }

    public function update(Request $request, $id)
    {
        return $this->userService->update($request, $id);
    }

    public function attachVault(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $user->vaults()->attach($request->vault_id);
    }

    public function toggleVault(Request $request, $userId)
    {
        $request->validate([
            'vault_id' => 'required|exists:vaults,id',
        ]);

        $vaultId = $request->vault_id;

        $existing = VaultAssign::where('user_id', $userId)
            ->where('vault_id', $vaultId)
            ->first();

        if ($existing) {
            // Toggle status
            $newStatus = $existing->status === 'active' ? 'inactive' : 'active';

            $existing->update([
                'status' => $newStatus
            ]);

            return response()->json([
                'message' => $newStatus === 'active'
                    ? 'Vault assigned successfully'
                    : 'Vault deactivated successfully',
                'action'  => $newStatus,
                'status'  => $newStatus
            ]);
        } else {
            // Create new assignment with active status
            VaultAssign::create([
                'user_id'  => $userId,
                'vault_id' => $vaultId,
                'roles'    => [],
                'status'   => 'active'        // Default active
            ]);

            return response()->json([
                'message' => 'Vault assigned successfully',
                'action'  => 'assigned',
                'status'  => 'active'
            ]);
        }
    }

    public function updateVaultRoles(Request $request, $userId, $vaultId)
    {
        // info("Updating vault roles for user $userId and vault $vaultId with data: " . json_encode($request->all()));

        $request->validate([
            // 'vault_id' => 'required|exists:vaults,id',
            'roles'    => 'present|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        // $vaultId = $request->vault_id;
        $roles   = $request->roles; // array of role names e.g. ['admin', 'editor']

        $user = User::findOrFail($userId);



        // Get the vault assignment
        $assignment = VaultAssign::where('user_id', $userId)
            ->where('vault_id', $vaultId)
            ->where('status', 'active')
            ->first();


        if (!$assignment) {
            return response()->json([
                'message' => 'No active vault assignment found for this user.'
            ], 404);
        }

        // Current roles stored in this vault assignment
        $existingRoles = $assignment->roles ?? [];

        // Roles to ADD (in new list but not in existing)
        $rolesToAdd = array_diff($roles, $existingRoles);

        // Roles to REMOVE (in existing but not in new list)
        $rolesToRemove = array_diff($existingRoles, $roles);

        // Assign new roles via Spatie (user-level permissions)
        foreach ($rolesToAdd as $roleName) {
            if (!$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }

        // Remove removed roles via Spatie
        foreach ($rolesToRemove as $roleName) {
            if ($user->hasRole($roleName)) {
                $user->removeRole($roleName);
            }
        }

        // Update JSON roles in vault_assigns table
        $assignment->update([
            'roles' => $roles // store the full updated array
        ]);

        return response()->json([
            'message'       => 'Vault roles updated successfully',
            'vault_id'      => $vaultId,
            'updated_roles' => $roles,
            'added'         => array_values($rolesToAdd),
            'removed'       => array_values($rolesToRemove),
        ]);
    }

    public function toggleStatus($userId)
    {
        $user = User::findOrFail($userId);
        $user->status = $user->status === 'inactive' ? 'active' : 'inactive';
        $user->save();

        return response()->json([
            'message' => 'User status updated',
            'status'  => $user->status
        ]);
    }

    public function archiveUser($userId)
    {
        $user = User::findOrFail($userId);
        $user->status = 'archived';
        $user->save();

        return response()->json([
            'message' => 'User archived',
            'status'  => 'archived'
        ]);
    }

    public function resetPassword($userId)
    {
        $user = User::findOrFail($userId);

        // Generate token — store in password_reset_tokens table
        $token = \Illuminate\Support\Str::random(64);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => hash('sha256', $token),
                'created_at' => now()
            ]
        );

        // Send email
        \Mail::to($user->email)->send(new \App\Mail\PasswordResetMail($user, $token));

        return response()->json(['message' => 'Password reset email sent']);
    }

    public function confirmResetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Direct token comparison — no hashing
        if (!$record || $record->token !== $request->token) {
            return response()->json(['message' => 'Invalid token'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json(['message' => 'Token expired'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Direct bcrypt — no Hash facade (JWT compatible)
        $user->password = bcrypt($request->password);
        $user->save();

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }
}
