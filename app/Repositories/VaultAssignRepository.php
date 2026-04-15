<?php

namespace App\Repositories;

use App\Models\VaultAssign;

class VaultAssignRepository
{
    public function create($data)
    {
        return VaultAssign::create($data);
    }

    public function getAssignVaultByUserIdAndVaultId($userId, $vaultId)
    {
        return VaultAssign::where('user_id', $userId)->where('vault_id', $vaultId)->get();
    }
}
