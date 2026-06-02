<?php

namespace App\Repositories;

use App\Models\VaultAuditConfig;

class VaultAuditConfigRepository
{

    public function getAll($request = null)
    {
        $query = VaultAuditConfig::query()->with('vault:id,name,vault_code,address');

        // Normalize: support null, array, or Request object
        $params = collect(
            is_null($request)    ? [] : (is_array($request)  ? $request : $request->all())
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

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
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
        VaultAuditConfig::create($data);
    }

    public function update($data, $id)
    {
        $data["config_by"] = auth()->user()->id;
        return $this->find($id)->update($data);
    }
}
