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
}
