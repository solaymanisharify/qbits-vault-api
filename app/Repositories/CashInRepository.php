<?php

namespace App\Repositories;

use App\Models\CashIn;
use App\Models\CashOut;

class CashInRepository
{
    protected $model;

    public function __construct(CashIn $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters = [])
    {
        $query = $this->model->newQuery();

        // === Eager load relationships to avoid N+1 queries ===
        $query->with([
            'user:id,name,email',
            'vault',
            'bags',
            'requiredVerifiers.user:id,name,email',
            'requiredApprovers.user:id,name,email',
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
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('bag_barcode', 'LIKE', $search . '%'); // Prefix search for better index usage
        }

        // === Status filters (indexed) ===
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['verifier_status'])) {
            $query->where('verifier_status', $filters['verifier_status']);
        }

        // === Date range filters (use index-friendly approach) ===
        // if (!empty($filters['from_date'])) {
        //     $query->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        // }

        // if (!empty($filters['to_date'])) {
        //     $query->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        // }

        // === Sorting ===
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // === Pagination ===
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100); // Limit between 1-100

        $results = $query->paginate($perPage)->appends($filters);




        // === Return formatted response ===
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

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function find($id)
    {
        return $this->model->with(['requiredVerifiers', 'requiredApprovers', 'vault', 'user'])->findOrFail($id);
    }

    public function findByBarcode(string $barcode)
    {
        return $this->model->where('bag_barcode', $barcode)->first();
    }

    public function update($id, array $data)
    {
        return $this->model->where('id', $id)->update($data);
    }

    public function getVerifierAllPendingCashInsByStatus($status)
    {
        return $this->model->where('verifier_status', $status)->get();
    }

    // Get pending CashIns for a specific verifier (not yet verified by them)
    public function getPendingForVerifier($userId)
    {
        return CashIn::where('verifier_status', 'pending')
            ->whereHas('requiredVerifiers', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('verified', false);
            })
            ->with(['requiredVerifiers', 'requiredApprovers'])
            ->get();
    }

    // Get verified CashIns pending approval
    public function getPendingForApprover()
    {
        return CashIn::where('verifier_status', 'verified')
            ->where('status', 'pending')
            ->with(['requiredVerifiers', 'requiredApprovers'])
            ->get();
    }

    public function getCashInsByVaultId($vaultId)
    {

        return CashIn::with('bags:id,barcode')
            ->where('vault_id', $vaultId)
            ->where('verifier_status', 'verified')
            ->where('approver_status', 'approved')
            ->whereDoesntHave('cashOut')
            ->get();
    }
}
