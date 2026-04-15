<?php

namespace App\Services;

use App\Repositories\VaultAssignRepository;



class VaultAssignService
{
    public function __construct(protected VaultAssignRepository $vaultAssignRepository) {}

    public function create($data)
    {
        return $this->vaultAssignRepository->create($data);
    }
    public function getAssignVaultByUserIdAndVaultId(int $userId, int $vaultId)
    {
        return $this->vaultAssignRepository->getAssignVaultByUserIdAndVaultId($userId, $vaultId);
    }

    public function toggleVaultAssign($request, $userId)
    {

        $vaultId = $request->vault_id;

        $existing = $this->vaultAssignRepository->getAssignVaultByUserIdAndVaultId($userId, $vaultId)->first();

        // $existing = VaultAssign::where('user_id', $userId)
        //     ->where('vault_id', $vaultId)
        //     ->first();

        if ($existing) {
            // Toggle status
            $newStatus = $existing->status === 'active' ? 'inactive' : 'active';

            $existing->update([
                'status' => $newStatus
            ]);

            return response()->json([
                'message' => $newStatus === 'active'
                    ? 'Vault assigned successfully'
                    : 'Vault deactivated successfully',
                'action'  => $newStatus,
                'status'  => $newStatus
            ]);
        } else {
            // Create new assignment with active status
            $this->create([
                'user_id'  => $userId,
                'vault_id' => $vaultId,
                'roles'    => [],
                'status'   => 'active'
            ]);

            return successResponse('Vault assigned successfully', null, 200);
        }
    }
}
