<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Kitchen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KitchenSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $kitchenTypes = [
            [
                'name' => ['ar' => 'مطبخ المشويات الرئيسي', 'en' => 'Main Grill Kitchen'],
                'password' => Hash::make('kitchen123'),
                'status' => true,
            ],
            [
                'name' => ['ar' => 'مطبخ الساندوتشات والوجبات السريعة', 'en' => 'Sandwich & Fast Food Kitchen'],
                'password' => Hash::make('kitchen123'),
                'status' => true,
            ],
            [
                'name' => ['ar' => 'مطبخ الحلويات والمخبوزات', 'en' => 'Bakery & Dessert Kitchen'],
                'password' => Hash::make('kitchen123'),
                'status' => true,
            ],
            [
                'name' => ['ar' => 'محطة المشروبات والعصائر', 'en' => 'Beverage & Juice Station'],
                'password' => Hash::make('kitchen123'),
                'status' => true,
            ],
        ];

        foreach ($branches as $branch) {
            foreach ($kitchenTypes as $index => $kt) {
                Kitchen::create([
                    'name' => $kt['name'],
                    'user_name' => "kitchen_{$branch->id}_".($index + 1),
                    'password' => $kt['password'],
                    'branch_id' => $branch->id,
                    'status' => $kt['status'],
                ]);
            }
        }
    }
}
