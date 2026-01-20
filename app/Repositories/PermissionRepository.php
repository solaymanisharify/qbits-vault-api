<?php

namespace App\Repositories;

use Spatie\Permission\Models\Permission;

class PermissionRepository
{
    public function __construct(protected Permission $model) {}

    public function permissions()
    {
        return $this->model->all();
    }

    public function getUserPermissions($id)
    {
        return $this->model->where('user_id', $id)->get();
    }
}
