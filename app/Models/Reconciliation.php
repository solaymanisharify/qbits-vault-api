<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $fillable = [
        'reconcile_tran_id',
        'vault_id',
        'status',
        'is_locked',
        'locked_until',
        'expected_balance',
        'counted_balance',
        'variance',
        'variance_type',
        'started_by',
        'completed_by',
        'from_date',
        'to_date',
        'expected_completion_at',
        'notes',
        'resolution_reason',
        'requires_escalation',
        'verifier_status',
        'approver_status',
    ];

    // Optional: you can also define casts for better type handling
    protected $casts = [
        'is_locked' => 'boolean',
        'requires_escalation' => 'boolean',
        'from_date' => 'datetime',
        'to_date' => 'datetime',
        'expected_completion_at' => 'datetime',
        'locked_until' => 'datetime',
        'expected_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    public function requiredVerifiers()
    {
        return $this->hasMany(ReconcileRequiredVerifier::class, 'reconcile_id', 'id');
    }

    public function requiredApprovers()
    {
        return $this->hasMany(ReconcileRequiredApprover::class, 'reconcile_id', 'id');
    }
}
