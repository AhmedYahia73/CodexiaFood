<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Manager',
            'Cashier',
            'Kitchen Staff',
            'Delivery Driver',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'manage'];
        $modules = ['products', 'categories', 'orders', 'branches', 'expenses', 'reports', 'settings'];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    Permission::firstOrCreate([
                        'role_id' => $role->id,
                        'name' => "{$action} {$module}",
                        'action' => "{$module}.{$action}",
                    ]);
                }
            }
        }
    }
}
