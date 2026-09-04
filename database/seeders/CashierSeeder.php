<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CashierSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            // Create Cashier Registers
            $pos1 = Cashier::create([
                'name' => "ماكينة كاشير 1 - {$branch->name}",
                'branch_id' => $branch->id,
            ]);

            $pos2 = Cashier::create([
                'name' => "ماكينة كاشير 2 - {$branch->name}",
                'branch_id' => $branch->id,
            ]);

            // Create Cashier Staff
            $cman1 = CashierMan::create([
                'name' => "كاشير أحمد علي ({$branch->name})",
                'password' => Hash::make('cashier123'),
                'cashier_id' => $pos1->id,
                'branch_id' => $branch->id,
            ]);

            $cman2 = CashierMan::create([
                'name' => "كاشيرة ياسمين محمود ({$branch->name})",
                'password' => Hash::make('cashier123'),
                'cashier_id' => $pos2->id,
                'branch_id' => $branch->id,
            ]);

            // Assign CashierMan back to Cashier
            $pos1->update(['cashier_man_id' => $cman1->id]);
            $pos2->update(['cashier_man_id' => $cman2->id]);
        }
    }
}
