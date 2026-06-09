<?php

namespace App\Repositories;

use App\Models\VaultAssign;

class VaultAssignRepository
{
    public function create($data)
    {
        return VaultAssign::create($data);
    }
    public function update($data, $id)
    {
        return VaultAssign::where('id', $id)->update($data);
    }
    public function delete($id)
    {
        return VaultAssign::where('id', $id)->delete();
    }

    public function findActiveVaultAssignUserByVaultId($vaultId)
    {
        return VaultAssign::where('vault_id', $vaultId)
            ->where('status', 'active')
            ->get(['user_id', 'roles']);
    }
    public function getAssignVaultByUserIdAndVaultId($userId, $vaultId)
    {
        return VaultAssign::with('user')->where('user_id', $userId)->where('vault_id', $vaultId)->get();
    }
    public function getAssignActiveVaultByUserId($userId)
    {
        return VaultAssign::where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('vault_id')
            ->toArray();
    }
    public function getAssignActiveVaultDetailsByUserId($userId)
    {
        return VaultAssign::where('user_id', $userId)
            ->where('status', 'active')
            ->get();
    }
    public function getAssignVaultByVaultIdAndRoleId($vaultId, $roleId)
    {

        info($vaultId);
        info($roleId);
        return VaultAssign::where('vault_id', $vaultId)
            ->where('status', 'active')
            ->whereJsonContains('roles', $roleId)
            ->with('user:id,name,email,status')
            ->get(['user_id', 'roles']);
    }
}
