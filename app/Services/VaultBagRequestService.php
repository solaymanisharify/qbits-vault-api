<?php

namespace App\Services;
use App\Repositories\VaultBagRequestRepository;

class VaultBagRequestService
{

    public function __construct(protected VaultBagRequestRepository $vaultBagRequestRepository, protected VaultAssignService $vaultAssignService) {}
    public function createBagRequest($data)
    {
        $requestPending =  $this->vaultBagRequestRepository->pendingBagRequestByVaultIdAndUserId($data['requester_id'], $data['vault_id']);
    
        if ($requestPending->exists()) {
            return errorResponse("You have already requested a bag for this vault.Wait for created bag", [], 400);
        }
        return $this->vaultBagRequestRepository->store($data);
    }

    public function getPendingVaultBagRequestByVaultId($vaultId)
    {

        if (empty($vaultId)) {
            $vaultId =  $this->vaultAssignService->getAssignActiveVaultByUserId(auth()->id());
        }

        return $this->vaultBagRequestRepository->getPendingVaultBagRequestByVaultId($vaultId);
    }
}
