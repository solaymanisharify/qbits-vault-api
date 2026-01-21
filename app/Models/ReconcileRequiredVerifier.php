<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconcileRequiredVerifier extends Model
{
    protected $fillable = [
        'reconcile_id',
        'user_id',
        'verified',
        'verified_at',
    ];

    public function reconcile()
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
