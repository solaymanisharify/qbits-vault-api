<?php

namespace App\Services;

use App\Repositories\VaultAssignRepository;



class VaultAssignService
{
    public function __construct(protected VaultAssignRepository $vaultAssignRepository, protected LogService $logService, protected UserService $userService) {}

    public function create($data)
    {
        return $this->vaultAssignRepository->create($data);
    }
    public function update($data, $id)
    {
        return $this->vaultAssignRepository->update($data, $id);
    }
    public function delete($id)
    {
        return $this->vaultAssignRepository->delete($id);
    }
    public function findActiveVaultAssignUserByVaultId($vaultId)
    {
        return $this->vaultAssignRepository->findActiveVaultAssignUserByVaultId($vaultId);
    }
    public function getAssignVaultByUserIdAndVaultId(int $userId, int $vaultId)
    {
        return $this->vaultAssignRepository->getAssignVaultByUserIdAndVaultId($userId, $vaultId);
    }
    public function getAssignActiveVaultByUserId(int $userId)
    {
        return $this->vaultAssignRepository->getAssignActiveVaultByUserId($userId);
    }

    public function getAssignVaultByVaultIdAndRoleId($vaultId, $roleId)
    {
        return $this->vaultAssignRepository->getAssignVaultByVaultIdAndRoleId($vaultId, $roleId);
    }

    public function getAssignActiveVaultDetailsByUserId(int $userId)
    {
        return $this->vaultAssignRepository->getAssignActiveVaultDetailsByUserId($userId);
    }


    public function toggleVaultAssign($request, $userId)
    {

        $vaultId = $request->vault_id;

        $existing = $this->vaultAssignRepository->getAssignVaultByUserIdAndVaultId($userId, $vaultId)->first();

        $user = $this->userService->findById($userId);

        if ($existing) {
            // Toggle status
            $newStatus = $existing->status === 'active' ? 'inactive' : 'active';

            $existing->update([
                'status' => $newStatus
            ]);

            $this->logService->activityLog(
                'updated',
                'user',
                "User {$user->name} ({$user->email}) assign {$newStatus} to this vault {$vaultId} ",
                []
            );

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
