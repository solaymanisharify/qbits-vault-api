<?php

namespace App\Services;

use App\Repositories\PermissionRepository;

class PermissionService
{

    public function __construct(
        protected PermissionRepository $permissionRepository,
        protected RoleService $roleService,
        protected UserService $userService

    ) {}

    public function permissions()
    {
        return $this->permissionRepository->permissions();
    }
    public function getUserPermissions($id)
    {
        return $this->permissionRepository->getUserPermissions($id);
    }
    public function UpdatePermission($permissions, $userId)
    {
        $user = $this->userService->findById($userId);

        $user->syncPermissions($permissions ?? []);

        return response()->json(['message' => 'Permissions updated successfully']);
    }
}
