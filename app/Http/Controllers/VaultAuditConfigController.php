<?php

namespace App\Http\Controllers;

use App\Services\VaultAuditConfigService;
use Illuminate\Http\Request;

class VaultAuditConfigController extends Controller
{
    public function __construct(protected VaultAuditConfigService $vaultAuditConfigService) {}

    public function index(Request $request)
    {

        return $this->vaultAuditConfigService->getAll($request->all());
    }

    public function store($data)
    {
        return $this->vaultAuditConfigService->create($data);
    }
    public function update(Request $request, $id)
    {
        return $this->vaultAuditConfigService->update($request->all(), $id);
    }
}
