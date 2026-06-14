<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultRequest;
use App\Models\Vault;
use App\Models\VaultBag;
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
        return $this->vaultService->store($request->all());
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

    public function addBagToVault(Request $request, $vaultId)
    {
        $vault = Vault::find($vaultId);

        if (!$vault) {
            return errorResponse('Vault not found.', [], 404);
        }

        $prefix = substr(str_pad($vault->vault_code, 3, '0', STR_PAD_LEFT), -3);
        $year   = date('y');

        $lastBag = VaultBag::where('vault_id', $vaultId)
            ->withTrashed()
            ->get()
            ->map(fn($b) => (int) (explode('_', $b->barcode)[1] ?? 0))
            ->max() ?? 0;

        $seq    = str_pad($lastBag + 1, 3, '0', STR_PAD_LEFT);
        $barcode              = "{$prefix}_{$seq}";
        $bagIdentifierBarcode = "QVB-{$year}-{$prefix}-{$seq}";

        $bag = VaultBag::create([
            'vault_id'               => $vaultId,
            'barcode'                => $barcode,
            'bag_identifier_barcode' => $bagIdentifierBarcode,
            'rack_number'            => $request->input('rack_number', '1'),
            'current_amount'         => 0,
            'is_active'              => true,
            'is_sealed'              => false,
        ]);

        return successResponse('Bag created successfully.', $bag, 201);
    }
}
