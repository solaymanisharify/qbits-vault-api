<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultBagCreateRequest extends Model
{
    protected $fillable = ['requester_id', 'bag_creator_id', 'vault_id', 'bag_id', 'bag_create_at', 'status'];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function bag()
    {
        return $this->belongsTo(VaultBag::class);
    }

    public function requestUser()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
    public function bagCreator()
    {
        return $this->belongsTo(User::class, 'bag_creator_id');
    }
}
