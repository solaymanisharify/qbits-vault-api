<?php

namespace App\Services;

use App\Repositories\VaultRepository;
use App\Models\Vault;
use Exception;
use Illuminate\Support\Facades\DB;

class VaultService
{
    public function __construct(protected VaultRepository $vaultReporsitory, protected VaultBagService $vaultBagService, protected VaultAuditConfigService $vaultAuditConfigService, protected LogService $logService) {}

    public function getAll(array $filters = [])
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->vaultReporsitory->index($filters, $perPage);
    }

    public function store($data)
    {
        // 1. Start the DB Transaction
        DB::beginTransaction();

        try {
            $vault = $this->vaultReporsitory->store($data);

            $this->logService->activityLog('created', 'vault', "New Vault #{$vault->name} ($vault->vault_code)");

            $vaultBalance = 0;

            // Loop through and store each bag
            foreach ($data["bags"] as $bag) {
                $data["vault_id"] = $vault->id;
                $data["barcode"] = $bag["barcode"];
                $data["bag_identifier_barcode"] = $bag["bag_identifier_barcode"];
                $data["rack_number"] = $bag["rack_number"];
                $data["current_amount"] = $bag["current_amount"];

                $newBag = $this->vaultBagService->store($data);

                $this->logService->activityLog('created', 'bag', "New bag #{$newBag->barcode} created for ($vault->name)", [
                    $newBag->barcode,
                    ['vault_id' => $vault->id, 'bag_id' => $newBag->barcode]
                ]);

                $vaultBalance += $bag["current_amount"];
            }

            // Update the vault with the calculated balance
            $data["balance"] = $vaultBalance;
            $this->vaultReporsitory->update($vault->id, $data);

            // Create the vault audit configuration
            $vaultAuditConfig["vault_id"] = $vault->id;
            $vaultConfig = $this->vaultAuditConfigService->create($vaultAuditConfig);

            $this->logService->activityLog('created', 'vault config', "New vault config created for ($vault->name)", [
                $vaultConfig->vault_id,
                ['vault_id' => $vaultConfig->vault_id, 'status' => $vaultConfig->status]
            ]);

            DB::commit();

            return successResponse("Successfully created vault", $vault, 201);
        } catch (Exception $e) {
            // 3. Rollback changes if anything fails
            DB::rollBack();

            return errorResponse("Failed to create vault: " . $e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        $vault = $this->vaultReporsitory->show($id);

        return successResponse("Successfully fetch vault", $vault, 200);
    }

    public function find(int $id)
    {
        return $this->vaultReporsitory->find($id);
    }

    public function edit(int $id): Vault
    {
        return $this->vaultReporsitory->edit($id);
    }

    public function update(int $id, array $data)
    {
        return $this->vaultReporsitory->update($id, $data);
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

        $vault = $this->vaultReporsitory->destroy($id);
        return successResponse('Vault deleted successfully.', $vault, 200);
    }
}
