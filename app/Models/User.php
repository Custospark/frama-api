<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'access_code',
        'role',
        'pin_hash',
        'branch_id',
        'is_active',
        'last_login_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'role' => Role::class,
            'password' => 'hashed',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function hasAtLeastRole(Role $role): bool
    {
        return $this->role instanceof Role && $this->role->atLeast($role);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === Role::PlatformAdmin;
    }

    public function isExecutive(): bool
    {
        return $this->role === Role::Executive;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    /** Executives and platform admins can view every branch. */
    public function canViewAllBranches(): bool
    {
        return $this->hasAtLeastRole(Role::Executive);
    }
}
