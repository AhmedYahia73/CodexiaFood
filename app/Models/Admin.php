<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'admin',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Permission::class,
            Role::class,
            'id',       // Foreign key on roles table...
            'role_id',  // Foreign key on permissions table...
            'role_id',  // Local key on admins table...
            'id'        // Local key on roles table...
        );
    }

    /**
     * Determine if admin has permission for a specific module and action.
     * If role_id is null, admin has full permissions.
     */
    public function hasPermission(string $module, string $action): bool
    {
        return PermissionService::hasPermission($this, $module, $action);
    }

    /**
     * Alias for hasPermission.
     */
    public function canAccess(string $module, string $action): bool
    {
        return $this->hasPermission($module, $action);
    }
}
