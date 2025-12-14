<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    protected $table = 'vaults';

    protected $fillable = [
        'vault_id',
        'name',
        'address',
        'balance',
        'total_racks',
        'total_bags',
        'last_cash_in',
        'last_cash_out',
        'verifiers',
        'status',
    ];

    protected $casts = [
        'total_bags' => 'array',
        'last_cash_in' => 'array',
        'last_cash_out' => 'array',
        'verifiers' => 'array',
        'status' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vault) {
            if (empty($vault->vault_id)) {
                $vault->vault_id = self::generateVaultId();
            }
        });
    }

    private static function generateVaultId(): string
    {
        // Get the last vault_id
        $lastVaultId = self::where('vault_id', 'LIKE', 'QBV%')
            ->orderBy('id', 'desc')
            ->value('vault_id');

        if ($lastVaultId) {
            // Extract number from QBV001
            $lastNumber = (int) substr($lastVaultId, 3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Generate QBV001, QBV002, etc.
        return 'QBV' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}