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
}
