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
        'role',
        'status'
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
