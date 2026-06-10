<?php

namespace App\Repositories;

use App\Models\ReconcileRequiredApprover;
use App\Models\ReconcileRequiredVerifier;

class ReconcileRequiredRepository
{

    public function createVerifier(array $data)
    {
        return ReconcileRequiredVerifier::create($data);
    }
    public function createApprover(array $data)
    {
        return ReconcileRequiredApprover::create($data);
    }

    public function getPendingVerifierByUserId($userId)
    {
        return ReconcileRequiredVerifier::with('reconcile.vault')->where('user_id', $userId)
            ->where('verified', false)
            ->get();
    }
}
