<?php

namespace App\Repositories;

use App\Models\Reconciliation;

class ReconcileRepository
{

    public function index($filters = [])
    {

        $query = Reconciliation::query();

        $query->with([
            'requiredVerifiers.user',
            'requiredApprovers.user',
            'startedBy',
            'completedBy',
            'varianceBags:id,vault_id,barcode,rack_number',
            'vault:id,vault_code,name',
        ]);

        // === Status filters (indexed) ===
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // if (!empty($filters['verifier_status'])) {
        //     $query->where('verifier_status', $filters['verifier_status']);
        // }

        // === Sorting ===
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // === Pagination ===
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100); // Limit between 1-100

        $results = $query->paginate($perPage)->appends($filters);

        return successResponse(
            "Successfully retrieved cash-ins",
            [
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                    'has_more' => $results->hasMorePages(),
                ],
                'filters' => array_filter($filters) // Return applied filters
            ],
            200
        );
    }

    public function findById($id)
    {
        return Reconciliation::with(['varianceBags:id,vault_id,barcode,rack_number,current_amount,denominations', 'vault.bags:id,vault_id,barcode,rack_number,current_amount'])->findOrFail($id);
    }
    public function update($data, $id)
    {
        return Reconciliation::where('id', $id)->update($data);
    }
    public function getLatestReconcile()
    {
        return Reconciliation::latest()->first();
    }

    public function checkReconcileLockStatusByVaultId($vaultId)
    {
        $reconciliation = Reconciliation::where('vault_id', $vaultId)->where('is_locked', true)->first();


        return successResponse(
            "Successfully retrieved",
            [
                'is_locked' => $reconciliation?->is_locked ?? false
            ],
            200
        );
    }

    public function getPendingReconcileByVaultId($vaultId)
    {
        return Reconciliation::where('vault_id', $vaultId)->where('status', 'pending')->first();
    }
    public function createReconcile($data)
    {
        return Reconciliation::create($data);
    }

    // Get pending reconciliations for a specific verifier (not yet verified by them)
    public function getPendingForVerifier($userId)
    {
        return Reconciliation::where('verifier_status', 'pending')
            ->whereHas('requiredVerifiers', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('verified', false);
            })
            ->with(['requiredVerifiers', 'requiredApprovers'])
            ->get();
    }

    // Get verified reconciliations pending approval
    public function getPendingForApprover()
    {
        return Reconciliation::where('verifier_status', 'verified')
            ->where('status', 'pending')
            ->with(['requiredVerifiers', 'requiredApprovers'])
            ->get();
    }
}
