<?php

namespace App\Repositories;

use App\Models\Reconciliation;

class ReconcileRepository
{

    public function index($filters = [])
    {

        $user = auth()->user();

        $query = Reconciliation::query();

        $query->with([
            'requiredVerifiers.user:id,name,email',
            'requiredApprovers.user:id,name,email',
            'startedBy:id,name,email',
            'completedBy',
            'varianceBags:id,vault_id,barcode,rack_number',
            'vault:id,vault_code,name',
        ]);

        if (!$user->hasRole('super-admin')) {

            // Force the cash_ins query to match an active row in the vault_assigns table
            $query->whereHas('vault.assignments', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('status', 'active');
            });
        }


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
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = min(max($perPage, 1), 100);

        $results = $query->paginate($perPage)->appends($filters);

        return successResponse(
            "Successfully retrieved cash-ins",
            $results,
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

    public function findActiveByVaultId($vaultId)
    {
        return Reconciliation::where('vault_id', $vaultId)
            ->where('status', '!=', 'completed') // Captures pending, ongoing, etc.
            ->first();
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
