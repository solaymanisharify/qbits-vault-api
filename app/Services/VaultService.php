<?php

namespace App\Services;

use App\Repositories\VaultRepository;
use App\Models\Vault;

class VaultService
{
    public function __construct(protected VaultRepository $repository, protected VaultBagService $vaultBagService, protected VaultAuditConfigService $vaultAuditConfigService) {}

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
            $data["rack_number"] = rand(1, 3);
            $data["current_amount"] = $bag["current_amount"];

            $this->vaultBagService->store($data);

            $vaultBalance += $bag["current_amount"];
        }

        $data["balance"] = $vaultBalance;

        $this->repository->update($vault->id, $data);

        // create vault audit
        $vaultAuditConfig["vault_id"] = $vault->id;
        $this->vaultAuditConfigService->create($vaultAuditConfig);
    }

    public function show(int $id)
    {
        $vault = $this->repository->show($id);

        return successResponse("Successfully fetch vault", $vault, 200);
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
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

    public function delete(int $id)
    {

        $vault = $this->find($id);

        if (!$vault) {
            return errorResponse('Vault not found.', [], 404);
        }

        // Check if any bags inside the vault have an amount
        $hasAmount = $vault->bags()->where('current_amount', '>', 0)->exists();

        if ($hasAmount) {
            return errorResponse('Vault cannot be deleted. Please cash out all bags before deleting.', [], 400);
        }

        $vault = $this->repository->destroy($id);
        return successResponse('Vault deleted successfully.', $vault, 200);
    }
}
