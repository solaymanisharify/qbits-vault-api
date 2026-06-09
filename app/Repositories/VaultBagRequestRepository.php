<?php

namespace App\Repositories;

use App\Models\VaultBagCreateRequest;

class VaultBagRequestRepository
{
    public function pendingBagRequestByVaultIdAndUserId($requesterId, $vaultId)
    {
        return VaultBagCreateRequest::where('requester_id', $requesterId)->where('vault_id', $vaultId)->whereNull('bag_id')->where('status', false);
    }
    public function store($data)
    {
        return VaultBagCreateRequest::create($data);
    }
    public function getPendingVaultBagRequestByVaultId($vaultId)
    {

        return VaultBagCreateRequest::with(['vault:id,name,vault_code', 'bag', 'requestUser:id,name', 'bagCreator:id,name'])
            ->whereIn('vault_id', (array) $vaultId)
            ->whereNull('bag_id')
            ->where('status', false)
            ->get();
    }
}
