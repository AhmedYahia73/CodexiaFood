<?php

namespace Database\Seeders;

use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Expense;
use App\Models\ExpenseList;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $expenseLists = ExpenseList::all();
        $cashiers = Cashier::all();
        $cashierMen = CashierMan::all();

        $amounts = [250.00, 450.50, 1200.00, 320.00, 850.00, 1500.00, 600.00, 950.00];

        foreach ($cashiers as $cashier) {
            foreach ($expenseLists->take(3) as $el) {
                Expense::create([
                    'expense_list_id' => $el->id,
                    'cashier_id' => $cashier->id,
                    'cashier_man_id' => $cashierMen->where('branch_id', $cashier->branch_id)->first()?->id,
                    'amount' => $amounts[array_rand($amounts)],
                ]);
            }
        }
    }
}
