<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => [
                    'ar' => 'الفرع الرئيسي - القاهرة',
                    'en' => 'Main Branch - Cairo',
                ],
                'user_name' => 'branch_cairo',
                'address' => 'شارع التحرير، الدقي، القاهرة',
                'watts' => '01012345678',
                'facebook' => 'https://facebook.com/codexa.cairo',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => [
                    'ar' => 'فرع الإسكندرية - الكورنيش',
                    'en' => 'Alexandria Branch',
                ],
                'user_name' => 'branch_alex',
                'address' => 'طريق الكورنيش، سيدي جابر، الإسكندرية',
                'watts' => '01123456789',
                'facebook' => 'https://facebook.com/codexa.alex',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => [
                    'ar' => 'فرع الجيزة - الشيخ زايد',
                    'en' => 'Sheikh Zayed Branch',
                ],
                'user_name' => 'branch_zayed',
                'address' => 'محور 26 يوليو، الشيخ زايد، الجيزة',
                'watts' => '01234567890',
                'facebook' => 'https://facebook.com/codexa.zayed',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => [
                    'ar' => 'فرع القاهرة الجديدة - التجمع',
                    'en' => 'New Cairo Branch',
                ],
                'user_name' => 'branch_newcairo',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'watts' => '01543219876',
                'facebook' => 'https://facebook.com/codexa.newcairo',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['user_name' => $branch['user_name']], $branch);
        }
    }
}
