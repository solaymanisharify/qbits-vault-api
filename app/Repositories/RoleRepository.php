<?php

namespace App\Repositories;

use App\Services\ActivityLoggerService;
use App\Services\LogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function __construct(protected Role $model, protected LogService $logService) {}

    public function index($request = null)
    {
        $query = $this->model->newQuery();

        // Get exclude parameter from array
        $exclude = $request['exclude'] ?? '';
        $excludeArray = array_map('trim', explode(',', $exclude));

        if (!in_array('permissions', $excludeArray)) {
            $query->with('permissions');
        }

        $roles = $query->get();

        return successResponse("Successfully fetch all roles", $roles, 200);
    }
    public function find($id)
    {
        return $this->model->whereIn('id', (array) $id)->get();
    }
    public function getByRoles(array $roles)
    {
        return $this->model->whereIn(DB::raw('LOWER(name)'), $roles)
            ->get()
            ->keyBy(fn($role) => strtolower($role->name));
    }
    public function create($data)
    {
        return $this->model->create($data);
    }
    public function update(array $data, int $id)
    {
        // 1. Fetch the existing model before it updates
        $role = $this->find($id);

        if (!$role) {
            return errorResponse('Role not found', [], 404);
        }

        // 2. Capture the state BEFORE the change
        $oldValues = $role->toArray();

        // 3. Perform the update on the instance
        $role->update($data);

        // 4. Capture the state AFTER the change
        $newValues = $role->refresh()->toArray();

        // 5. Fire your standard-compliant audit log
        ActivityLoggerService::updated(
            $role,
            'role',
            "Role \"{$role->name}\" (ID: #{$role->id})",
            $oldValues,
            $newValues
        );

        return successResponse("Role updated successfully", $role);
    }
    public function delete(int $id)
    {

        $role = $this->find($id);

        if ($role instanceof Collection) {
            $role = $role->first();
        }

        if (!$role) {
            return errorResponse('Role not found or already deleted', [], 404);
        }

        $this->logService->activityLog(
            'deleted',
            'role',
            "Deleted Role #{$role->name}",
            [
                $role->toArray(),
                [
                    'role_name' => $role->name,
                    'role_id' => $role->id,

                ]
            ]
        );

        // 3. Perform the actual deletion
        $role->delete();

        return successResponse("Role deleted successfully");
    }
}
