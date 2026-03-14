<?php

namespace App\Repositories;

use App\Models\Vault;
use App\Models\VaultBag;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VaultRepository
{
    // public function index(array $filters = [], ?int $perPage = 15)
    // {
    //     $query = Vault::with('bags')
    //         ->when($filters['search'] ?? null, function ($q, $search) {
    //             $q->where('name', 'like', "%{$search}%")
    //                 ->orWhere('vault_id', 'like', "%{$search}%")
    //                 ->orWhere('address', 'like', "%{$search}%");
    //         })
    //         ->when(
    //             $filters['user_id'] ?? null,
    //             fn($q, $userId) =>
    //             $q->where('user_id', $userId)
    //         )
    //         ->when($filters['status'] ?? null, function ($q, $status) {
    //             $isOpen = filter_var($status, FILTER_VALIDATE_BOOLEAN);
    //             $q->whereJsonPath('status.open', '=', $isOpen);
    //         })
    //         ->latest();

    //     return $perPage ? $query->paginate($perPage) : $query->get();
    // }


    public function index(array $filters = [], int $perPage = 15)
    {
        $sortBy  = in_array($filters['sort_by'] ?? '', ['name', 'balance', 'created_at', 'total_bags'])
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Vault::query()
            ->select([
                'id',
                'vault_id',
                'name',
                'address',
                'balance',
                'total_racks',
                'total_bags',
                'last_cash_in',
                'last_cash_out',
                'status',
                'created_at',
            ])
            ->withCount('bags')
            ->with(['bags:id,vault_id,barcode,bag_identifier_barcode,rack_number,current_amount,is_active,is_sealed'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                // Wrap in a grouped where so OR doesn't bleed into other clauses
                $q->where(function ($q2) use ($search) {
                    $q2->where('name',     'like', "%{$search}%")
                        ->orWhere('vault_id', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['status']  ?? null, function ($q, $status) {
                $isOpen = filter_var($status, FILTER_VALIDATE_BOOLEAN);
                $q->whereJsonPath('status.open', '=', $isOpen);
            })
            ->orderBy($sortBy, $sortDir);

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Store a new vault in database.  
     */
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

    /**
     * Get a vault instance for editing.
     */
    public function edit(int $id): Vault
    {
        return Vault::findOrFail($id);
    }

    /**
     * Update an existing vault.
     */
    // public function update(int $id, array $data): bool
    // {
    //     $vault = Vault::findOrFail($id);

    //     // Separate bags from vault fields
    //     $bags    = $data['bags'] ?? [];
    //     $vaultData = collect($data)->except(['bags'])->toArray();

    //     // Update vault core fields
    //     $updated = $vault->update($vaultData);

    //     // Handle bags — update existing, create new
    //     if (!empty($bags)) {
    //         foreach ($bags as $bagData) {
    //             $existingBag = VaultBag::where('vault_id', $vault->id)
    //                 ->where('barcode', $bagData['barcode'])
    //                 ->first();

    //             if ($existingBag) {
    //                 // Update existing bag
    //                 $existingBag->update([
    //                     'bag_identifier_barcode' => $bagData['bag_identifier_barcode'] ?? $existingBag->bag_identifier_barcode,
    //                     'rack_number'            => $bagData['rack_number'] ?? $existingBag->rack_number,
    //                     'current_amount'         => $bagData['current_amount'] ?? $existingBag->current_amount,
    //                 ]);
    //             } else {
    //                 // Create new bag
    //                 VaultBag::create([
    //                     'vault_id'               => $vault->id,
    //                     'barcode'                => $bagData['barcode'],
    //                     'bag_identifier_barcode' => $bagData['bag_identifier_barcode'] ?? null,
    //                     'rack_number'            => $bagData['rack_number'] ?? null,
    //                     'current_amount'         => $bagData['current_amount'] ?? 0,
    //                     'is_active'              => true,
    //                     'is_sealed'              => false,
    //                 ]);
    //             }
    //         }

    //         // Recalculate vault totals from all its bags
    //         $vault->refresh();
    //         $totalAmount = $vault->bags()->sum('current_amount');
    //         $totalBags   = $vault->bags()->count();

    //         $vault->update([
    //             'balance'    => $totalAmount,
    //             'total_bags' => $totalBags,
    //         ]);
    //     }

    //     return $updated;
    // }

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
            $existingBags = VaultBag::where('vault_id', $vault->id)->get();

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

                    // Safe to soft-delete
                    $existingBag->appendHistory('deleted', "Bag removed from vault during vault update", [
                        'vault_id'   => $vault->id,
                        'vault_name' => $vault->name,
                    ]);

                    $existingBag->update(['is_active' => false, 'deleted_reason' => 'Removed during vault update']);
                    $existingBag->delete(); // soft delete

                    ActivityLoggerService::deleted(
                        $existingBag,
                        'bag',
                        $existingBag->barcode,
                        'Removed during vault update',
                        ['vault_id' => $vault->id, 'vault_name' => $vault->name]
                    );

                    $deletedBarcodes[] = $existingBag->barcode;
                }
            }

            // ── 4. Upsert incoming bags ───────────────────────────────────────
            foreach ($incomingBags as $bagData) {
                $existingBag = VaultBag::where('vault_id', $vault->id)
                    ->where('barcode', $bagData['barcode'])
                    ->first();

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

                        $existingBag->appendHistory('updated', 'Bag fields updated', ['changes' => $changes]);

                        ActivityLoggerService::updated(
                            $existingBag,
                            'bag',
                            $existingBag->barcode,
                            $oldBagSnap,
                            $existingBag->fresh()->toArray(),
                            ['vault_id' => $vault->id]
                        );
                    }
                } else {
                    // --- Create new bag ---
                    $newBag = VaultBag::create([
                        'vault_id'               => $vault->id,
                        'barcode'                => $bagData['barcode'],
                        'bag_identifier_barcode' => $bagData['bag_identifier_barcode'] ?? null,
                        'rack_number'            => $bagData['rack_number'] ?? null,
                        'current_amount'         => $bagData['current_amount'] ?? 0,
                        'is_active'              => true,
                        'is_sealed'              => false,
                    ]);

                    $newBag->appendHistory('created', 'Bag created and added to vault', [
                        'vault_id'   => $vault->id,
                        'vault_name' => $vault->name,
                    ]);

                    ActivityLoggerService::created(
                        $newBag,
                        'bag',
                        $newBag->barcode,
                        $newBag->toArray(),
                        ['vault_id' => $vault->id]
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

        // ── 6. Log vault update ───────────────────────────────────────────────
        ActivityLoggerService::updated(
            $vault,
            'vault',
            $vault->name,
            $oldSnap,
            $vault->fresh()->toArray(),
            ['bags_deleted' => $deletedBarcodes]
        );

        return [
            'success'         => empty($errors),
            'errors'          => $errors,           // non-deleted bags with amounts
            'deleted_barcodes' => $deletedBarcodes,
        ];
    }
    /**
     * Delete a vault.
     */
    public function destroy(int $id): bool
    {
        $vault = Vault::findOrFail($id);

        return $vault->delete();
    }
}
