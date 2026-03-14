<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'subject_type',
        'subject_id',
        'subject_label',
        'event',
        'module',
        'description',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta'       => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic subject (VaultBag, Vault, Transaction, etc.)
     * Usage: $log->subject → the model instance
     */
    public function subject()
    {
        if (!$this->subject_type || !$this->subject_id) return null;
        return $this->subject_type::find($this->subject_id);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForSubject($query, $type, $id)
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }
}
