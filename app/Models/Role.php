<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    /**
     * Sync permissions to this role.
     */
    public function syncPermissions(mixed $permissions): void
    {
        app(PermissionService::class)->syncRolePermissions($this, $permissions);
    }

    /**
     * Get permissions grouped by module name.
     *
     * @return array<string, array<string>>
     */
    public function groupedPermissions(): array
    {
        return app(PermissionService::class)->groupPermissions($this->permissions);
    }

    /**
     * Check if this role has permission for a specific module and action.
     */
    public function hasPermission(string $module, string $action): bool
    {
        $permissions = $this->relationLoaded('permissions')
            ? $this->permissions
            : $this->permissions()->get();

        $normalizedModule = strtolower($module);
        $normalizedAction = strtolower($action);

        return $permissions->contains(function (Permission $perm) use ($normalizedModule, $normalizedAction) {
            $permName = strtolower($perm->name);
            $permAction = strtolower($perm->action);

            $moduleMatch = ($permName === $normalizedModule
                || $permName === '*'
                || $permName === 'all');

            if (! $moduleMatch) {
                return false;
            }

            return $permAction === $normalizedAction
                || $permAction === 'all'
                || $permAction === '*';
        });
    }
}
