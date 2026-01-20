<?php

namespace App\Services;

use App\Repositories\RoleRepository;

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
        return $this->roleRepository->create($data);
    }
    public function update($data, $id)
    {
        return $this->roleRepository->update($data, $id);
    }
    public function delete($id)
    {
        return $this->roleRepository->delete($id);
    }
}
