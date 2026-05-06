<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultBagCreateRequest extends Model
{
    protected $fillable = ['requester_id', 'bag_creator_id', 'vault_id', 'bag_id', 'bag_create_at', 'status'];
}
