<?php

namespace App\Repositories;

use App\Models\VaultBagCreateRequest;

class VaultBagRequestRepository
{
    public function store($data)
    {
        return VaultBagCreateRequest::create($data);
    }
}
