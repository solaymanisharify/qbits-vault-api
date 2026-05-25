<?php

namespace App\Services;

use App\Repositories\RoleRepository;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\RoleAlreadyExists;

class RoleService
{

    public function __construct(protected RoleRepository $roleRepository) {}

    public function index($request = null)
    {
        return $this->roleRepository->index($request);
    }
    public function find(array $id)
    {
        return $this->roleRepository->find($id);
    }
    public function create($data)
    {
        try {
            $role = $this->roleRepository->create($data);
            return successResponse("Role created successfully", $role, 200);
        } catch (RoleAlreadyExists $e) {
            return errorResponse(
                "A role `{$data['name']}` already exists.",
                [],
                422
            );
        }
    }
    public function update($data, $id)
    {
        return $this->roleRepository->update($data, $id);
    }
    public function delete($id)
    {
        // 1. This now returns a Collection because of the repository update
        $rolesCollection = $this->roleRepository->find($id);

        // 2. Extract the first individual Role model from the collection
        $role = $rolesCollection->first();

        if (!$role) {
            return errorResponse(['message' => 'Role not found'], [], 404);
        }

        // 3. Now you can safely call ->users() on the single model instance!
        if ($role->users()->exists()) {
            return errorResponse(
                "Cannot delete the role `{$role->name}` because it is currently assigned to one or more users.",
                [],
                422
            );
        }

        $this->roleRepository->delete($id);

        return successResponse(['message' => "Role deleted successfully"], [], 200);
    }
}
