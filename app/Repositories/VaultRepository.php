<?php

namespace App\Repositories;

use App\Models\Vault;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VaultRepository
{
    /**
     * Get all vaults with optional pagination.
     */
    public function index(array $filters = [], ?int $perPage = 15)
    {
        $query = Vault::with('bags')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('vault_id', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            })
            ->when(
                $filters['user_id'] ?? null,
                fn($q, $userId) =>
                $q->where('user_id', $userId)
            )
            ->when($filters['status'] ?? null, function ($q, $status) {
                $isOpen = filter_var($status, FILTER_VALIDATE_BOOLEAN);
                // Recommended for Laravel 9.19+ (cleaner for booleans)
                $q->whereJsonPath('status.open', '=', $isOpen);

                // Alternative (works on older versions)
                // $q->whereJsonContains('status->open', $isOpen);
            })
            ->latest();

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new vault record (returns the model with form data, usually for "create" view).
     */
    // public function create(): Vault
    // {
    //     return new Vault();
    // }

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
        return Vault::findOrFail($id);
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
    public function update(int $id, array $data): bool
    {
        $vault = Vault::findOrFail($id);

        return $vault->update($data);
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
