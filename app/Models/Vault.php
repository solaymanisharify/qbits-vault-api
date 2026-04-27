<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    protected $table = 'vaults';

    protected $fillable = [
        'vault_code',
        'name',
        'address',
        'balance',
        'total_racks',
        'total_bags',
        'last_cash_in',
        'last_cash_out',
        'verifiers',
        'status',
        'bag_balance_limit',
    ];

    protected $casts = [
        'total_bags' => 'array',
        'last_cash_in' => 'array',
        'last_cash_out' => 'array',
        'verifiers' => 'array',
        'status' => 'array',
    ];



    public function bags()
    {
        return $this->hasMany(VaultBag::class);
    }
    
}