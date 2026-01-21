<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconcileRequiredApprover extends Model
{
    protected $fillable = [
        'reconcile_id',
        'user_id',
        'approved',
        'approved_at',
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
