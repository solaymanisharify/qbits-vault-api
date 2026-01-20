<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashoutRequiredVerifier extends Model
{
    protected $fillable = ['cash_out_id', 'user_id', 'verified', 'verified_at'];

    public function cashOut()
    {
        return $this->belongsTo(CashOut::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
