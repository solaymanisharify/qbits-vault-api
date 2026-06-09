<?php

namespace App\Repositories;

use App\Models\CashInRequiredApprover;
use App\Models\CashInRequiredVerifier;

class CashInRequiredRepository
{
    public function create(array $data)
    {
        return CashInRequiredVerifier::create($data);
    }
    public function createApprover(array $data)
    {
        return CashInRequiredApprover::create($data);
    }
    public function getPendingVerifierByUserId($userId)
    {
        return CashInRequiredVerifier::with(['cashIn.vault'])
            ->where('user_id', $userId)
            ->where('verified', false)
            ->get();
    }
    public function getPendingApproveByUserId($userId)
    {
        return CashInRequiredApprover::with(['cashIn.vault'])
            ->where('user_id', $userId)
            ->where('approved', false)
            ->get();
    }
}
