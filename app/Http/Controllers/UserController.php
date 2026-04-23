<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\VaultAssign;
use App\Services\PermissionService;
use App\Services\UserService;
use App\Services\VaultAssignService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected PermissionService $permissionService,
        protected VaultAssignService $vaultAssignService
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
        $targetUser = $this->userService->findById($userId);

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
        $targetUser = $this->userService->findById($userId);

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
        $user = $this->userService->findById($userId);
        $user->vaults()->attach($request->vault_id);
    }

    public function toggleVaultAssign(Request $request, $userId)
    {
        $request->validate([
            'vault_id' => 'required|exists:vaults,id',
        ]);

        return $this->vaultAssignService->toggleVaultAssign($request, $userId);

        // $vaultId = $request->vault_id;

        // $existing = VaultAssign::where('user_id', $userId)
        //     ->where('vault_id', $vaultId)
        //     ->first();

        // if ($existing) {
        //     // Toggle status
        //     $newStatus = $existing->status === 'active' ? 'inactive' : 'active';

        //     $existing->update([
        //         'status' => $newStatus
        //     ]);

        //     return response()->json([
        //         'message' => $newStatus === 'active'
        //             ? 'Vault assigned successfully'
        //             : 'Vault deactivated successfully',
        //         'action'  => $newStatus,
        //         'status'  => $newStatus
        //     ]);
        // } else {
        //     // Create new assignment with active status
        //     VaultAssign::create([
        //         'user_id'  => $userId,
        //         'vault_id' => $vaultId,
        //         'roles'    => [],
        //         'status'   => 'active'        // Default active
        //     ]);

        //     return response()->json([
        //         'message' => 'Vault assigned successfully',
        //         'action'  => 'assigned',
        //         'status'  => 'active'
        //     ]);
        // }


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

        $user = $this->userService->findById($userId);



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

    public function toggleUserStatus($userId)
    {
        return $this->userService->toggleUserStatus($userId);
    }

    public function archiveUser($userId)
    {
        return $this->userService->archiveUser($userId);
    }

    public function resetPassword($userId)
    {
        return $this->userService->resetPassword($userId);
    }

    public function confirmResetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        return $this->userService->confirmResetPassword($request);
    }


    public function downloadId($id)
    {
        $user = User::with('roles', 'vaultAssignments')->findOrFail($id);

        // Convert images to base64 directly from storage
        $profileImg = $this->imageToBase64($user->img);
        $nidFront   = $this->imageToBase64($user->nid_front_img);
        $nidBack    = $this->imageToBase64($user->nid_back_img);

        $pdf = Pdf::loadView('pdf.user-id', [
            'user'       => $user,
            'profileImg' => $profileImg,
            'nidFront'   => $nidFront,
            'nidBack'    => $nidBack,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$user->name}_Identity_Report.pdf");
    }

    private function imageToBase64($path)
    {
        if (!$path) return null;

        // Strip full URL if stored as full URL
        $relativePath = str_replace(config('app.url') . '/storage/', '', $path);
        $fullPath = storage_path('app/public/' . $relativePath);

        if (!file_exists($fullPath)) return null;

        $mime = mime_content_type($fullPath);
        $data = base64_encode(file_get_contents($fullPath));
        return "data:{$mime};base64,{$data}";
    }
}
