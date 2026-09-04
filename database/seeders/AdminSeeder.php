<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $managerRole = Role::where('name', 'Manager')->first();

        $admins = [
            [
                'name' => 'المدير العام (Super Admin)',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole?->id,
            ],
            [
                'name' => 'مدير النظام (System Manager)',
                'password' => Hash::make('password'),
                'role_id' => $managerRole?->id,
            ],
            [
                'name' => 'أحمد محمود (Branch Admin)',
                'password' => Hash::make('password'),
                'role_id' => $managerRole?->id,
            ],
            [
                'name' => 'سارة علي (Operations Manager)',
                'password' => Hash::make('password'),
                'role_id' => $managerRole?->id,
            ],
            [
                'name' => 'محمد حسن (Support Admin)',
                'password' => Hash::make('password'),
                'role_id' => $managerRole?->id,
            ],
        ];

        foreach ($admins as $admin) {
            Admin::firstOrCreate(['name' => $admin['name']], $admin);
        }
    }
}
