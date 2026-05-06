<?php

namespace App\Services;

use App\Models\VaultBagCreateRequest;
use App\Repositories\VaultBagRequestRepository;

class VaultBagRequestService
{

    public function __construct(protected VaultBagRequestRepository $vaultBagRequestRepository) {}
    public function createBagRequest($data)
    {
        //check if already requested for the bag for the vault

        $requestPending = VaultBagCreateRequest::where('requester_id', $data['requester_id'])->where('vault_id', $data['vault_id'])->whereNull('bag_id')->where('status', false);

        if ($requestPending->exists()) {
            return errorResponse("You have already requested a bag for this vault.Wait for created bag", [], 400);
        }
        return $this->vaultBagRequestRepository->store($data);
    }
}
