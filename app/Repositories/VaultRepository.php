<?php

namespace App\Repositories;

use App\Models\Vault;
use App\Models\VaultAssign;
use App\Models\VaultBag;
use App\Services\ActivityLoggerService;
use App\Services\LogService;
use App\Services\VaultBagService;

class VaultRepository
{

    public function __construct(protected VaultBagService $vaultBagService, protected LogService $logService) {}

    public function index(array $filters = [], int $perPage = 15)
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin') || $user->hasRole('super_admin');

        $sortBy  = in_array($filters['sort_by'] ?? '', ['name', 'balance', 'created_at', 'total_bags'])
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';


        $assignedVaultIds = [];
        if (!$isSuperAdmin) {
            $assignedVaultIds = VaultAssign::where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('vault_id')
                ->toArray();

            if (empty($assignedVaultIds)) {
                return successResponse('Successfully retrieved vaults', collect([]), 200);
            }
        }

        $query = Vault::query()
            ->select([
                'id',
                'vault_code',
                'name',
                'address',
                'balance',
                'bag_min_bal_limit',
                'bag_balance_limit',
                'total_racks',
                'total_bags',
                'last_cash_in',
                'last_cash_out',
                'status',
                'created_at',
            ])
            // Single query for bags — derive count from the loaded collection
            // avoids hitting the bags table twice (withCount + with)
            ->with(['bags:id,vault_id,barcode,bag_identifier_barcode,rack_number,current_amount,is_active,is_sealed'])
            ->when(!$isSuperAdmin, fn($q) => $q->whereIn('id', $assignedVaultIds))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name',        'like', "%{$search}%")
                        ->orWhere('vault_code', 'like', "%{$search}%")
                        ->orWhere('address',    'like', "%{$search}%");
                });
            })
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['status']  ?? null, function ($q, $status) {
                $isOpen = filter_var($status, FILTER_VALIDATE_BOOLEAN);
                $q->whereJsonPath('status.open', '=', $isOpen);
            })
            ->orderBy($sortBy, $sortDir);

        $results = $perPage ? $query->paginate($perPage) : $query->get();

        // Append bags_count derived from already-loaded relation (no extra query)
        $results->getCollection()->transform(function ($vault) {
            $vault->bags_count = $vault->bags->count();
            return $vault;
        });

        return successResponse('Successfully retrieved vaults', $results, 200);
    }

    public function store($data)
    {
        return Vault::create($data);
    }

    /**
     * Find a vault by ID.
     */
    public function show(int $id): ?Vault
    {
        return Vault::with('bags')->findOrFail($id);
    }
    public function find(int $id): ?Vault
    {
        return Vault::findOrFail($id);
    }

    /**
     * Get a vault instance for editing.
     */
    public function edit(int $id): Vault
    {
        return Vault::findOrFail($id);
    }

    public function update(int $id, array $data): array
    {
        $vault    = Vault::findOrFail($id);
        $oldSnap  = $vault->toArray();

        // ── 1. Separate bags payload from vault fields ────────────────────────
        $incomingBags = $data['bags'] ?? [];
        $vaultData    = collect($data)->except(['bags'])->toArray();

        // ── 2. Update vault core fields ───────────────────────────────────────
        $vault->update($vaultData);

        $deletedBarcodes = [];
        $errors          = [];

        if (!empty($incomingBags)) {
            $incomingBarcodes = collect($incomingBags)->pluck('barcode')->toArray();

            // ── 3. Find bags that exist in DB but are missing in payload ──────
            $existingBags = $this->vaultBagService->getVaultBagByVaultid($vault->id);

            foreach ($existingBags as $existingBag) {
                if (!in_array($existingBag->barcode, $incomingBarcodes)) {
                    // Guard: cannot delete if bag has a non-zero amount
                    if ((float) $existingBag->current_amount > 0) {
                        $errors[] = [
                            'barcode' => $existingBag->barcode,
                            'message' => "Bag {$existingBag->barcode} has ৳{$existingBag->current_amount} — zero the amount before deleting.",
                        ];
                        continue;
                    }


                    $existingBag->update(['is_active' => false, 'deleted_reason' => 'Removed during vault update']);
                    $existingBag->delete(); // soft delete

                    // Log
                    $this->logService->activityLog(
                        'deleted',
                        'bag',
                        "Removed during vault update",
                        [
                            $existingBag->barcode,
                            ['vault_id' => $vault->id, 'vault_name' => $vault->name]
                        ]
                    );

                    $deletedBarcodes[] = $existingBag->barcode;
                }
            }

            // ── 4. Upsert incoming bags ───────────────────────────────────────
            foreach ($incomingBags as $bagData) {

                $existingBag = $this->vaultBagService->findVaultbagWithBarcodeAndVaultId($vault->id, $bagData['barcode']);

                if ($existingBag) {
                    // --- Update existing bag ---
                    $oldBagSnap = $existingBag->toArray();

                    $changes = [];
                    $updatePayload = [];

                    foreach (['bag_identifier_barcode', 'rack_number', 'current_amount'] as $field) {
                        if (isset($bagData[$field]) && (string) $existingBag->$field !== (string) $bagData[$field]) {
                            $changes[$field] = ['from' => $existingBag->$field, 'to' => $bagData[$field]];
                            $updatePayload[$field] = $bagData[$field];
                        }
                    }

                    if (!empty($updatePayload)) {
                        $existingBag->update($updatePayload);

                        // Log
                        $this->logService->activityLog(
                            'updated',
                            'bag',
                            $existingBag->barcode,
                            [
                                $oldBagSnap,
                                $existingBag->fresh()->toArray(),
                                ['vault_id' => $vault->id]
                            ]
                        );
                    }
                } else {

                    $payload = [
                        'vault_id'               => $vault->id,
                        'barcode'                => $bagData['barcode'],
                        'bag_identifier_barcode' => $bagData['bag_identifier_barcode'] ?? null,
                        'rack_number'            => $bagData['rack_number'] ?? null,
                        'current_amount'         => $bagData['current_amount'] ?? 0,
                        'is_active'              => true,
                        'is_sealed'              => false,
                    ];

                    $newBag = $this->vaultBagService->store($payload);

                    $this->logService->activityLog(
                        'created',
                        'bag',
                        "New Bag #$newBag->barcode",
                        [
                            $newBag->toArray(),
                        ]
                    );
                }
            }
        }

        // ── 5. Recalculate vault totals ───────────────────────────────────────
        $vault->refresh();
        $totalAmount = $vault->bags()->sum('current_amount');
        $totalBags   = $vault->bags()->count();

        $vault->update([
            'balance'    => $totalAmount,
            'total_bags' => $totalBags,
        ]);


        $this->logService->activityLog(
            'updated',
            'vault',
            $vault->name,
            [
                $oldSnap,
                $vault->fresh()->toArray(),
                ['bags_deleted' => $deletedBarcodes]
            ]
        );

        return [
            'success'         => empty($errors),
            'errors'          => $errors,
            'deleted_barcodes' => $deletedBarcodes,
        ];
    }
    /**
     * Delete a vault.
     */
    public function destroy(int $id)
    {
        $vault = Vault::findOrFail($id);

        $vault->delete();

        $this->logService->activityLog('deleted', 'vault', " Vault #{$vault->name} ($vault->vault_code) deleted");

        return successResponse('Vault deleted successfully.', $vault, 200);
    }
}
