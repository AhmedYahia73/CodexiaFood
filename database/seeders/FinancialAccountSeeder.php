<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\FinancialAccount;
use Illuminate\Database\Seeder;

class FinancialAccountSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $accounts = [
            ['name' => ['ar' => 'الخزينة الرئيسية', 'en' => 'Main Cash Treasury'], 'icon' => 'heroicon-o-banknotes', 'balance' => 50000.00],
            ['name' => ['ar' => 'حساب البنك الأهلي', 'en' => 'National Bank Account'], 'icon' => 'heroicon-o-building-library', 'balance' => 150000.00],
            ['name' => ['ar' => 'عهد المصروفات النثرية', 'en' => 'Petty Cash Account'], 'icon' => 'heroicon-o-wallet', 'balance' => 5000.00],
            ['name' => ['ar' => 'حساب المبيعات المباشرة', 'en' => 'Direct Sales Account'], 'icon' => 'heroicon-o-shopping-cart', 'balance' => 0.00],
        ];

        foreach ($branches as $branch) {
            foreach ($accounts as $acc) {
                FinancialAccount::create([
                    'name' => $acc['name'],
                    'icon' => $acc['icon'],
                    'balance' => $acc['balance'],
                    'status' => true,
                    'branch_id' => $branch->id,
                ]);
            }
        }
    }
}
