<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashOut extends Model
{
    protected $fillable = ["user_id", "cash_in_id","vault_id", "tran_id", "cash_out_amount", "request_amount", "verifier_status", "approver_status", "note"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bags()
    {
        return $this->belongsTo(VaultBag::class, 'bag_id', 'id');
    }
    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }
    public function cashOutBags()
    {
        return $this->hasMany(CashOutBag::class, 'cash_out_id', 'id');
    }
    public function custodian()
    {
        return $this->belongsTo(CustodianCashHistory::class, 'id', 'cash_out_id');
    }


    public function requiredVerifiers()
    {
        return $this->hasMany(CashoutRequiredVerifier::class);
    }

    public function requiredApprovers()
    {
        return $this->hasMany(CashoutRequiredApprover::class);
    }
}
