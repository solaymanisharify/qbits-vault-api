<?php

namespace App\Services;

use App\Repositories\VaultRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Vault;

class VaultService
{
    public function __construct(protected VaultRepository $repository) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->repository->index($filters, $perPage);
    }

    // public function create($data)
    // {
    //     $data['vault_id'] = uniqid();
    //     return $this->repository->create();
    // }

    public function store(array $data): Vault
    {
        return $this->repository->store($data);
    }

    public function show(int $id): ?Vault
    {
        return $this->repository->show($id);
    }

    public function edit(int $id): Vault
    {
        return $this->repository->edit($id);
    }

    public function update(int $id, array $data): bool
    {
        // Business logic before/after update if needed
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        // You could dispatch events, soft-delete related data, etc.
        return $this->repository->destroy($id);
    }

}
