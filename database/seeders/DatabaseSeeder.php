<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            AdminSeeder::class,
            BranchSeeder::class,
            KitchenSeeder::class,
            CashierSeeder::class,
            DeliverySeeder::class,
            HallSeeder::class,
            HallTableSeeder::class,
            FinancialAccountSeeder::class,
            ExpenseListSeeder::class,
            ExpenseSeeder::class,
            PaymentMethodSeeder::class,
            TaxSeeder::class,
            DiscountSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            MaterialSeeder::class,
            ProductSeeder::class,
            VariationAndOptionSeeder::class,
            AddonSeeder::class,
            ProductRecipeSeeder::class,
            ManufacturingListSeeder::class,
            WasteSeeder::class,
        ]);
    }
}
