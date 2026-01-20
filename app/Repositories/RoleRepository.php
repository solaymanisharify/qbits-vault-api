<?php

namespace App\Repositories;

use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function __construct(protected Role $model) {}

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
        return $this->model->whereIn('id', $id)->get();
    }
    public function create($data)
    {
        return $this->model->create($data);
    }
    public function update($data, $id)
    {
        return $this->find($id)->update($data);
    }
    public function delete($id)
    {
        return $this->find($id)->delete();
    }
}
