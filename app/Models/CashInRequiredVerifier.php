<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashInRequiredVerifier extends Model
{
    protected $fillable = ['cash_in_id', 'user_id', 'verified', 'verified_at'];

    public function cashIn()
    {
        return $this->belongsTo(CashIn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
