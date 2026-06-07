<?php

namespace App\Repositories;

use App\Models\VaultAssign;

class VaultAssignRepository
{
    public function create($data)
    {
        return VaultAssign::create($data);
    }

    public function findActiveVaultAssignUserByVaultId($vaultId)
    {
        return VaultAssign::where('vault_id', $vaultId)
            ->where('status', 'active')
            ->get(['user_id', 'roles']);
    }
    public function getAssignVaultByUserIdAndVaultId($userId, $vaultId)
    {
        return VaultAssign::where('user_id', $userId)->where('vault_id', $vaultId)->get();
    }
    public function getAssignActiveVaultByUserId($userId)
    {
        return VaultAssign::where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('vault_id')
            ->toArray();
    }
}
