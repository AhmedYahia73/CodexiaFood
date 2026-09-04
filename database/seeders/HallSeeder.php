<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Hall;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $halls = [
            ['name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Dining Hall'], 'status' => true],
            ['name' => ['ar' => 'صالة كبار الزوار VIP', 'en' => 'VIP Lounge'], 'status' => true],
            ['name' => ['ar' => 'الجلسات الخارجية (التراس)', 'en' => 'Outdoor Terrace'], 'status' => true],
            ['name' => ['ar' => 'صالة العائلات', 'en' => 'Family Dining Hall'], 'status' => true],
        ];

        foreach ($branches as $branch) {
            foreach ($halls as $hall) {
                Hall::create([
                    'name' => $hall['name'],
                    'branch_id' => $branch->id,
                    'status' => $hall['status'],
                ]);
            }
        }
    }
}
