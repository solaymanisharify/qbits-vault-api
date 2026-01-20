<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // Optional, if you want soft deletes

class VaultBag extends Model
{
    use HasFactory;
    // use SoftDeletes; // Uncomment if you add softDeletes() in migration

    protected $table = 'vault_bags';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'vault_id',
        'bag_identifier_barcode',
        'barcode',
        'rack_number',
        'current_amount',
        'is_sealed',
        'is_active',
        'denominations',

        // Last cash-in details
        'last_cash_in_amount',
        'last_cash_in_at',
        'last_cash_in_by',
        'last_cash_in_tran_id',

        // Last cash-out details
        'last_cash_out_tran_id',
        'last_cash_out_amount',
        'last_cash_out_at',
        'last_cash_out_by',

        // Statistics
        'total_cash_in_attempts',
        'total_cash_out_attempts',
        'total_successful_deposits',
        'total_failed_attempts',

        // Metadata
        'notes',
        'history',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'current_amount'          => 'decimal:2',
        'last_cash_in_amount'     => 'decimal:2',
        'last_cash_out_amount'    => 'decimal:2',

        'last_cash_in_at'         => 'datetime',
        'last_cash_out_at'        => 'datetime',

        'is_sealed'               => 'boolean',
        'is_active'               => 'boolean',

        'history'                 => 'array', // or 'json' – stores full audit trail
        // 'notes' is text, no cast needed
    ];

    /**
     * Relationships
     */

    // Belongs to a Vault
    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    // Last cash-in performed by (User)
    public function lastCashInBy()
    {
        return $this->belongsTo(User::class, 'last_cash_in_by');
    }

    // Last cash-out performed by (User)
    public function lastCashOutBy()
    {
        return $this->belongsTo(User::class, 'last_cash_out_by');
    }

    /**
     * Optional: Scope to get only active bags
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Optional: Append a log entry to history (JSON array)
     */
    public function addHistoryLog(array $log)
    {
        $history = $this->history ?? [];

        $history[] = array_merge($log, [
            'timestamp' => now()->toDateTimeString(),
        ]);

        $this->history = $history;
        $this->save();
    }
}
