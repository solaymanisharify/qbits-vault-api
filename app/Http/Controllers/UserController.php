<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\UserService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
     public function __construct(protected UserService $userService) {}

    public function index()
    {
        return User::with('roles', 'permissions')->get();
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

    // Add delete, update as needed
}
