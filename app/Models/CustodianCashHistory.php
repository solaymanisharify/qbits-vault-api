<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustodianCashHistory extends Model
{
    protected $fillable = ['custodian_id', 'vault_id', 'cash_out_id', 'amount', 'status', 'verified_at'];

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id', 'id');
    }
}
