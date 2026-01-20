<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashoutRequiredApprover extends Model
{
    protected $fillable = [
        'cash_out_id',
        'user_id',
        'approved',
        'approved_at',
    ];

    public function cashOut()
    {
        return $this->belongsTo(CashOut::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
