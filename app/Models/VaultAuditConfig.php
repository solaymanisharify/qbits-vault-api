<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultAuditConfig extends Model
{
    protected $fillable = ['vault_id', 'interval', 'time', 'day', 'last_audit_date', 'failed_audits', 'config_by','status'];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }
}
