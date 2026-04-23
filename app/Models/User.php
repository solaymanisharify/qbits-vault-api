<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'email_verified_at',
        'role',
        'current_address',
        'current_division',
        'current_district',
        'current_thana',
        'permanent_address',
        'permanent_division',
        'permanent_district',
        'permanent_thana',
        'verified',
        'status',
        'kyc_verified_at',
        'img',
        'nid_front_image',
        'nid_back_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }

    public function vaultAssignments()
    {
        return $this->hasMany(VaultAssign::class);
    }

    public function vaults()
    {
        return $this->hasManyThrough(
            Vault::class,
            VaultAssign::class,
            'user_id',
            'id',
            'id',
            'vault_id'
        );
    }

    public function getEffectivePermissions()
    {
        // Get all permissions from roles
        $rolePermissionIds = $this->roles
            ->load('permissions')
            ->pluck('permissions.*.id')
            ->flatten()
            ->unique()
            ->toArray();

        // Get overrides for this user
        $overrides = DB::table('user_permission_overrides')
            ->where('user_id', $this->id)
            ->get()
            ->keyBy('permission_id');

        // Start with role permissions
        $effective = $rolePermissionIds;

        // Apply overrides
        $allPermissions = Permission::pluck('id')->toArray();

        foreach ($allPermissions as $permId) {
            if (isset($overrides[$permId])) {
                if ($overrides[$permId]->granted) {
                    if (!in_array($permId, $effective)) {
                        $effective[] = $permId;
                    }
                } else {
                    $effective = array_filter($effective, fn($id) => $id !== $permId);
                }
            }
        }

        return $effective;
    }
}
