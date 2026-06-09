<?php

namespace App\Repositories;

use App\Models\CashoutRequiredApprover;
use App\Models\CashoutRequiredVerifier;

class CashOutRequiredRepository
{

    public function create(array $data)
    {
        return CashoutRequiredVerifier::create($data);
    }
    public function createApprover(array $data)
    {
        return CashoutRequiredApprover::create($data);
    }
    public function getPendingVerificationByUserId($userId)
    {
        return CashoutRequiredVerifier::with(['cashOut.vault'])
            ->where('user_id', $userId)
            ->where('verified', false)
            ->get();
    }
    public function getPendingApproveByUserId($userId)
    {
        return CashoutRequiredApprover::with(['cashOut.vault'])
            ->where('user_id', $userId)
            ->where('approved', false)
            ->get();
    }
}
