<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallTable;
use Illuminate\Database\Seeder;

class HallTableSeeder extends Seeder
{
    public function run(): void
    {
        $halls = Hall::all();

        foreach ($halls as $hall) {
            for ($i = 1; $i <= 6; $i++) {
                HallTable::create([
                    'name' => "طاولة {$i} (Table {$i})",
                    'branch_id' => $hall->branch_id,
                    'hall_id' => $hall->id,
                    'status' => true,
                ]);
            }
        }
    }
}
