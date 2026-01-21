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
            // // 'branch:id,name,code',
            // 'items' => function ($q) {
            //     $q->select('id', 'cash_in_id', 'denomination', 'quantity', 'amount');
            // }
        ]);

        // === Role-based access control ===
        // if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
        //     $query->where('user_id', auth()->id());
        // }

        // === Search by bag_barcode (indexed column) ===
        // if (!empty($filters['search'])) {
        //     $search = trim($filters['search']);
        //     $query->where('bag_barcode', 'LIKE', $search . '%'); // Prefix search for better index usage
        // }

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
                    ->where('verified', false); // hasn't verified yet
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
