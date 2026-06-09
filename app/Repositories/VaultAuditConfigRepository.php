<?php

namespace App\Repositories;

use App\Models\VaultAssign;
use App\Models\VaultAuditConfig;

class VaultAuditConfigRepository
{

    public function getAll($request = null)
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin') || $user->hasRole('super_admin');

        $assignedVaultIds = [];
        if (!$isSuperAdmin) {
            $assignedVaultIds = VaultAssign::where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('vault_id')
                ->toArray();

            // If a non-admin has no active vault assignments, return an empty collection immediately
            if (empty($assignedVaultIds)) {
                return successResponse('Successfully retrieved vaults', collect([]), 200);
            }
        }

        $query = VaultAuditConfig::query()->with('vault:id,name,vault_code,address');

        // 1. Apply the Vault Assignment Filter here
        if (!$isSuperAdmin) {
            $query->whereIn('vault_id', $assignedVaultIds);
        }

        // Normalize: support null, array, or Request object
        $params = collect(
            is_null($request) ? [] : (is_array($request) ? $request : $request->all())
        );

        // Search
        if ($params->has('search') && filled($params->get('search'))) {
            $search = $params->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filters
        $filters = ['status', 'type', 'created_by'];
        foreach ($filters as $filter) {
            if ($params->has($filter) && filled($params->get($filter))) {
                $query->where($filter, $params->get($filter));
            }
        }

        // 2. FIXED BUG: Changed $filters to $params for sorting parameters
        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = (int) ($params->get('per_page', 20));
        $results = $query->paginate($perPage)->withQueryString();

        return successResponse('Vault audit configs fetched successfully', $results, 200);
    }

    public function find($id)
    {
        return VaultAuditConfig::findOrFail($id);
    }
    public function create($data)
    {
       return VaultAuditConfig::create($data);
    }

    public function update($data, $id)
    {
        $data["config_by"] = auth()->user()->id;
        return $this->find($id)->update($data);
    }
}
