<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultAssign extends Model
{
    protected $fillable = [
        'vault_id',
        'user_id',
        'roles',
        'status',
    ];

    protected $casts = [
        'roles' => 'array',
    ];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
