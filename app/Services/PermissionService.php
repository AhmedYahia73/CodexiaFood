<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionService
{
    /**
     * Get the master list of all modules and their supported actions.
     *
     * @return array<string, array<string>>
     */
    public static function getAvailablePermissions(): array
    {
        return [
            'addon' => ['all', 'view', 'add', 'update', 'delete'],
            'admin' => ['all', 'view', 'add', 'update', 'delete'],
            'banner' => ['all', 'view', 'add', 'delete'],
            'branch' => ['all', 'view', 'add', 'update', 'delete'],
            'business_setup' => ['all', 'view', 'update'],
            'captain_order' => ['all', 'view', 'add', 'update', 'delete'],
            'cashier' => ['all', 'view', 'add', 'update', 'delete'],
            'cashier_man' => ['all', 'view', 'add', 'update', 'delete'],
            'category' => ['all', 'view', 'add', 'update', 'delete'],
            'dashboard' => ['all', 'view'],
            'delivery' => ['all', 'view', 'add', 'update', 'delete'],
            'discount' => ['all', 'view', 'add', 'update', 'delete'],
            'expense_list' => ['all', 'view', 'add', 'update', 'delete'],
            'financial_account' => ['all', 'view', 'add', 'update', 'delete'],
            'hall' => ['all', 'view', 'add', 'update', 'delete'],
            'hall_table' => ['all', 'view', 'add', 'update', 'delete'],
            'inventory' => ['all', 'view', 'add', 'update', 'delete'],
            'kitchen' => ['all', 'view', 'add', 'update', 'delete'],
            'kitchen_product' => ['all', 'view', 'update'],
            'manufacturing' => ['all', 'view', 'add'],
            'material' => ['all', 'view', 'add', 'update', 'delete'],
            'order' => ['all', 'view', 'add', 'update', 'delete'],
            'payment_method' => ['all', 'view', 'add', 'update', 'delete'],
            'product' => ['all', 'view', 'add', 'update', 'delete'],
            'product_manufacturing' => ['all', 'view', 'add', 'update', 'delete'],
            'product_recipe' => ['all', 'view', 'add', 'update', 'delete'],
            'purchase' => ['all', 'view', 'add', 'update', 'delete'],
            'purchase_due' => ['all', 'view', 'update'],
            'report' => ['all', 'view'],
            'role' => ['all', 'view', 'add', 'update', 'delete'],
            'sale' => ['all', 'view', 'add', 'update', 'delete'],
            'sale_due' => ['all', 'view', 'update'],
            'saler' => ['all', 'view', 'add', 'update', 'delete'],
            'shift' => ['all', 'view', 'add', 'update', 'delete'],
            'supplier' => ['all', 'view', 'add', 'update', 'delete'],
            'tax' => ['all', 'view', 'add', 'update', 'delete'],
            'unit' => ['all', 'view', 'add', 'update', 'delete'],
            'waste' => ['all', 'view', 'add', 'update', 'delete'],
        ];
    }

    /**
     * Normalize permissions input from various acceptable formats into clean [['name' => ..., 'action' => ...]].
     *
     * Supports:
     * 1. Map/Associative array: ['purchase' => ['view', 'add'], 'order' => 'all']
     * 2. List of objects: [['name' => 'purchase', 'action' => 'view'], ...]
     * 3. List of strings: ['purchase.view', 'purchase:add', 'product.all']
     *
     * @return array<int, array{name: string, action: string}>
     */
    public function normalizePermissions(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($input as $key => $value) {
            // Case 1: Associative map: ['purchase' => ['view', 'add']] or ['purchase' => 'all']
            if (is_string($key) && ! is_numeric($key)) {
                $module = strtolower(trim($key));
                $actions = is_array($value) ? $value : [$value];

                foreach ($actions as $action) {
                    if (is_string($action)) {
                        $this->pushPermission($normalized, $seen, $module, strtolower(trim($action)));
                    }
                }

                continue;
            }

            // Case 2: Array of objects: [['name' => 'purchase', 'action' => 'view']]
            if (is_array($value) && isset($value['name'], $value['action'])) {
                $this->pushPermission(
                    $normalized,
                    $seen,
                    strtolower(trim((string) $value['name'])),
                    strtolower(trim((string) $value['action']))
                );

                continue;
            }

            // Case 3: List of string representations: 'purchase.view', 'purchase:view', 'purchase,view'
            if (is_string($value)) {
                $separator = null;
                if (str_contains($value, '.')) {
                    $separator = '.';
                } elseif (str_contains($value, ':')) {
                    $separator = ':';
                } elseif (str_contains($value, ',')) {
                    $separator = ',';
                }

                if ($separator !== null) {
                    [$module, $action] = explode($separator, $value, 2);
                    $this->pushPermission(
                        $normalized,
                        $seen,
                        strtolower(trim($module)),
                        strtolower(trim($action))
                    );
                }
            }
        }

        return $normalized;
    }

    /**
     * Push a normalized permission if not duplicate.
     *
     * @param  array<int, array{name: string, action: string}>  $normalized
     * @param  array<string, bool>  $seen
     */
    protected function pushPermission(array &$normalized, array &$seen, string $module, string $action): void
    {
        if ($module === '' || $action === '') {
            return;
        }

        $signature = "{$module}:{$action}";
        if (isset($seen[$signature])) {
            return;
        }

        $seen[$signature] = true;
        $normalized[] = [
            'name' => $module,
            'action' => $action,
        ];
    }

    /**
     * Sync permissions to a role in a transaction.
     */
    public function syncRolePermissions(Role $role, mixed $permissionsInput): void
    {
        $normalized = $this->normalizePermissions($permissionsInput);

        DB::transaction(function () use ($role, $normalized) {
            $role->permissions()->delete();

            if (! empty($normalized)) {
                $now = now();
                $rows = array_map(function (array $item) use ($role, $now) {
                    return [
                        'role_id' => $role->id,
                        'name' => $item['name'],
                        'action' => $item['action'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $normalized);

                Permission::insert($rows);
            }
        });
    }

    /**
     * Group a collection/array of permissions into ['module' => ['action1', 'action2']].
     *
     * @param  iterable<Permission>|array<array{name: string, action: string}>  $permissions
     * @return array<string, array<string>>
     */
    public function groupPermissions(iterable $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $name = is_array($permission) ? ($permission['name'] ?? null) : $permission->name;
            $action = is_array($permission) ? ($permission['action'] ?? null) : $permission->action;

            if ($name && $action) {
                $grouped[$name][] = $action;
            }
        }

        foreach ($grouped as $module => $actions) {
            $grouped[$module] = array_values(array_unique($actions));
        }

        return $grouped;
    }

    /**
     * Determine module name and action name from controller class and method name.
     *
     * @return array{0: string, 1: string} [module, action]
     */
    public static function resolveModuleAndAction(?string $controllerClass, ?string $methodName, ?string $httpMethod = null): array
    {
        $module = self::resolveModuleFromController($controllerClass);
        $action = self::resolveActionFromMethod($methodName, $httpMethod);

        return [$module, $action];
    }

    /**
     * Resolve module name from controller class.
     */
    public static function resolveModuleFromController(?string $controllerClass): string
    {
        if (! $controllerClass) {
            return 'general';
        }

        $classBasename = class_basename($controllerClass);
        $rawName = preg_replace('/Controller$/', '', $classBasename) ?: $classBasename;

        // Custom mappings
        $mapping = [
            'ProductManufactring' => 'product_manufacturing',
            'Manufactring' => 'manufacturing',
            'InventoryRecipe' => 'inventory',
            'InventoryMaterial' => 'inventory',
            'InventoryAddon' => 'inventory',
            'ExpenseList' => 'expense_list',
            'FinancialAccount' => 'financial_account',
            'HallTable' => 'hall_table',
            'CaptainOrder' => 'captain_order',
            'CashierMan' => 'cashier_man',
            'BusinessSetup' => 'business_setup',
            'PaymentMethod' => 'payment_method',
            'KitchenProduct' => 'kitchen_product',
            'PurchaseDue' => 'purchase_due',
            'SaleDue' => 'sale_due',
            'ProductRecipe' => 'product_recipe',
        ];

        if (isset($mapping[$rawName])) {
            return $mapping[$rawName];
        }

        return Str::snake($rawName);
    }

    /**
     * Resolve action (view, add, update, delete, etc.) from method name and HTTP method.
     */
    public static function resolveActionFromMethod(?string $methodName, ?string $httpMethod = null): string
    {
        $method = $methodName ? strtolower($methodName) : '';

        // View actions (index, lists, show, options, reports, etc.)
        $viewMethods = [
            'index', 'show', 'lists', 'select', 'selectoptions', 'getspecification',
            'history', 'pending', 'overdue', 'paid', 'unpaid', 'posorders',
            'onlineorders', 'kitchenproducts', 'unassigned', 'startshiftreport',
            'statistics', 'permissions',
        ];

        if (in_array($method, $viewMethods, true)) {
            return 'view';
        }

        // Add / Create actions
        $addMethods = ['store', 'create', 'manufacture'];
        if (in_array($method, $addMethods, true)) {
            return 'add';
        }

        // Update actions
        $updateMethods = [
            'update', 'edit', 'patch', 'updatestatus', 'assign', 'changestatus',
            'updateactualstocks', 'updateitem', 'pay',
        ];
        if (in_array($method, $updateMethods, true)) {
            return 'update';
        }

        // Delete actions
        $deleteMethods = ['destroy', 'delete'];
        if (in_array($method, $deleteMethods, true)) {
            return 'delete';
        }

        // Fallback by HTTP method
        if ($httpMethod) {
            $upper = strtoupper($httpMethod);

            return match ($upper) {
                'POST' => 'add',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'view',
            };
        }

        return 'view';
    }

    /**
     * Check if an admin has permission for a specific module and action.
     */
    public static function hasPermission(Admin $admin, string $module, string $action): bool
    {
        // Rule: If admin has no role_id, full permissions
        if ($admin->role_id === null) {
            return true;
        }

        $role = $admin->relationLoaded('role')
            ? $admin->getRelation('role')
            : $admin->role()->with('permissions')->first();

        if (! $role) {
            return false;
        }

        $permissions = $role->relationLoaded('permissions')
            ? $role->permissions
            : $role->permissions()->get();

        $normalizedModule = strtolower($module);
        $normalizedAction = strtolower($action);

        return $permissions->contains(function (Permission $perm) use ($normalizedModule, $normalizedAction) {
            $permName = strtolower($perm->name);
            $permAction = strtolower($perm->action);

            $moduleMatch = ($permName === $normalizedModule
                || $permName === '*'
                || $permName === 'all'
                || ($normalizedModule === 'inventory_recipe' && $permName === 'inventory')
                || ($normalizedModule === 'inventory_material' && $permName === 'inventory')
                || ($normalizedModule === 'inventory_addon' && $permName === 'inventory')
            );

            if (! $moduleMatch) {
                return false;
            }

            return $permAction === $normalizedAction
                || $permAction === 'all'
                || $permAction === '*';
        });
    }
}
