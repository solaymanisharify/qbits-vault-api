<?php

namespace App\Services;

use App\Repositories\CustodianRepository;

class CustodianService
{
    public function __construct(protected CustodianRepository $custodianRepository) {}

    public function verifyReceivedCash($cashOutId)
    {
        return $this->custodianRepository->verifyReceivedCash($cashOutId);
    }
}
