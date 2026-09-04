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
                'name' => 'الفرع الرئيسي - القاهرة (Main Branch - Cairo)',
                'address' => 'شارع التحرير، الدقي، القاهرة',
                'watts' => '01012345678',
                'facebook' => 'https://facebook.com/codexa.cairo',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => 'فرع الإسكندرية - الكورنيش (Alexandria Branch)',
                'address' => 'طريق الكورنيش، سيدي جابر، الإسكندرية',
                'watts' => '01123456789',
                'facebook' => 'https://facebook.com/codexa.alex',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => 'فرع الجيزة - الشيخ زايد (Sheikh Zayed Branch)',
                'address' => 'محور 26 يوليو، الشيخ زايد، الجيزة',
                'watts' => '01234567890',
                'facebook' => 'https://facebook.com/codexa.zayed',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
            [
                'name' => 'فرع القاهرة الجديدة - التجمع (New Cairo Branch)',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'watts' => '01543219876',
                'facebook' => 'https://facebook.com/codexa.newcairo',
                'status' => true,
                'password' => Hash::make('branch123'),
            ],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['name' => $branch['name']], $branch);
        }
    }
}
