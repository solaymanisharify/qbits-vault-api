<?php

namespace App\Services;

use App\Repositories\VaultRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Vault;

class VaultService
{
    public function __construct(protected VaultRepository $repository, protected VaultBagService $vaultBagService) {}

    public function getAll(array $filters = [])
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->repository->index($filters, $perPage);
    }

    public function store(array $data)
    {
        $vault = $this->repository->store($data);

        $vaultBalance = 0;

        foreach ($data["bags"] as $bag) {
            $data["vault_id"] = $vault->id;
            $data["barcode"] = $bag["barcode"];
            $data["bag_identifier_barcode"] = $bag["bag_identifier_barcode"];
            $data["rack_number"] = $bag["rack_number"];
            $data["current_amount"] = $bag["current_amount"];

            $this->vaultBagService->store($data);

            $vaultBalance += $bag["current_amount"];
        }

        $data["balance"] = $vaultBalance;
        $this->repository->update($vault->id, $data);
    }

    public function show(int $id)
    {
        $vault = $this->repository->show($id);

        return successResponse("Successfully fetch vault", $vault, 200);
    }

    public function edit(int $id): Vault
    {
        return $this->repository->edit($id);
    }

    public function update(int $id, array $data)
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
