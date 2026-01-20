<?php

namespace App\Services;

use App\Repositories\PermissionRepository;

class PermissionService
{

    public function __construct(protected PermissionRepository $permissionRepository, protected RoleService $roleService) {}

    public function permissions()
    {
        return $this->permissionRepository->permissions();
    }
    public function getUserPermissions($id)
    {
        return $this->permissionRepository->getUserPermissions($id);
    }
    public function update($request, $id)
    {
        $ids = is_array($id) ? $id : [$id];

        $roles = $this->roleService->find($ids);

        $roles->each(function ($role) use ($request) {
            $role->syncPermissions($request['permissions']);
        });

        return response()->json(['message' => 'Permissions updated successfully']);
    }
}
