<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultRequest;
use App\Models\Vault;
use App\Services\VaultBagRequestService;
use App\Services\VaultBagService;
use App\Services\VaultService;
use Illuminate\Http\Request;

class VaultController extends Controller
{
    public function __construct(protected VaultService $vaultService, protected VaultBagService $vaultBagService, protected VaultBagRequestService $vaultBagRequestService) {}

    public function index(Request $request)
    {
        $filters = $request->only('search', 'user_id', 'status', 'sort_by', 'sort_dir', 'per_page');

        return $this->vaultService->getAll($filters);
    }

    public function store(StoreVaultRequest $request)
    {
        $vault = $this->vaultService->store($request);
        return $vault;
    }

    public function show($id)
    {
        return $this->vaultService->show($id);
    }

    public function edit($id)
    {
        return $this->vaultService->edit($id);
    }

    public function update(Request $request, $id)
    {
        return $this->vaultService->update($id, $request->all());
    }

    public function destroy($id)
    {
        return $this->vaultService->delete($id);
    }

    public function getBag(Request $request, $id)
    {
        return $this->vaultBagService->getBagById($request, $id);
    }
    public function getBagByBagId($id)
    {
        return $this->vaultBagService->getBagByBagId($id);
    }
    public function createBagRequest(Request $request)
    {
        return $this->vaultBagRequestService->createBagRequest($request->all());
    }
}
