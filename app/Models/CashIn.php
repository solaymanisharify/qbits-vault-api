<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashIn extends Model
{
    protected $fillable = ['user_id', 'tran_id', 'vault_id', 'orders', 'bag_id', 'cash_in_amount', 'denominations', 'verifier_status', 'status'];

    protected $casts = [
        'denominations' => 'array',
        'orders' => 'array',
        'cash_in_amount' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bags()
    {
        return $this->belongsTo(VaultBag::class, 'bag_id','id');
    }
    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function verifications()
    {
        return $this->hasMany(CashInVerification::class);
    }
    public function requiredVerifiers()
    {
        return $this->hasMany(CashInRequiredVerifier::class);
    }

    public function requiredApprovers()
    {
        return $this->hasMany(CashInRequiredApprover::class);
    }
}
