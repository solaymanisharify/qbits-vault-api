<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashIn extends Model
{
    protected $fillable = ['user_id', 'vault_id', 'orders', 'bag_barcode', 'cash_in_amount', 'denominations', 'verifier_status', 'status'];

    protected $casts = [
        'denominations' => 'array',
        'orders' => 'array',
        'cash_in_amount' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }
}
