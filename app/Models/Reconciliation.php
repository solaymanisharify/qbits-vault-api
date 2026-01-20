<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $fillable = [
        'reconcile_tran_id',
        'scope_type',
        'scope_id',
        'status',
        'is_locked',
        'locked_until',
        'expected_balance',
        'counted_balance',
        'variance',
        'variance_type',
        'started_by',
        'completed_by',
        'started_at',
        'completed_at',
        'expected_completion_at',
        'notes',
        'resolution_reason',
        'requires_escalation',
    ];

    // Optional: you can also define casts for better type handling
    protected $casts = [
        'is_locked' => 'boolean',
        'requires_escalation' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_completion_at' => 'datetime',
        'locked_until' => 'datetime',
        'expected_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'variance' => 'decimal:2',
    ];
}
