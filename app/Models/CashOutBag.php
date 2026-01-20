<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashOutBag extends Model
{
    protected $fillable = ['cash_out_id','bags_id','verifier_status','status','note'];

    public function bag()
    {
        return $this->belongsTo(VaultBag::class, 'bags_id', 'id');
    }
}
