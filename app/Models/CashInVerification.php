<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashInVerification extends Model
{
    protected $fillable = ['cash_in_id', 'user_id', 'action', 'note'];

    public function cashIn()
    {
        return $this->belongsTo(CashIn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
