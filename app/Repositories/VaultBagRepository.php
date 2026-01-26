<?php

namespace App\Repositories;

use App\Models\VaultBag;
use Illuminate\Support\Facades\Log;

class VaultBagRepository
{
    public function store($data)
    {
        return VaultBag::create($data);
    }

    public function getBagById($id, $search = null)
    {
        try {

            $query = VaultBag::where('vault_id', $id);

            // Add search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('barcode', 'LIKE', "%{$search}%")
                        ->orWhere('rack_number', 'LIKE', "%{$search}%");
                });
            }

            $result = $query->get(['id', 'barcode', 'denominations', 'rack_number', 'current_amount']);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in getBagById repository', [
                'vault_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function update($data, $id)
    {
        return VaultBag::where('id', $id)->update($data);
    }
    public function getBagByBagId($id)
    {
        return VaultBag::find($id);
    }
}
