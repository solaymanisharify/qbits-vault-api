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
}
