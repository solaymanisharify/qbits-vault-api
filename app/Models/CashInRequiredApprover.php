<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashInRequiredApprover extends Model
{

    protected $fillable = [
        'cash_in_id',
        'user_id',
        'approved',
        'approved_at',
    ];

    public function cashIn()
    {
        return $this->belongsTo(CashIn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
