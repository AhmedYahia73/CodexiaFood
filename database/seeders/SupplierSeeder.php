<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'شركة الوطنية للحوم والدواجن (National Meat & Poultry Co.)',
                'phone' => '01009988776',
                'email' => 'contact@nationalmeat.com',
                'balance' => 15000.00,
            ],
            [
                'name' => 'مزارع الخير الخضروات والفاكهة (El Kheer Produce Farms)',
                'phone' => '01122334455',
                'email' => 'sales@elkheerfarms.com',
                'balance' => 4500.00,
            ],
            [
                'name' => 'مصانع جهينة للألبان والأجبان (Juhayna Dairy Products)',
                'phone' => '01233445566',
                'email' => 'info@juhayna.com',
                'balance' => 8200.00,
            ],
            [
                'name' => 'مطاحن ومطابخ الصفا لمستلزمات المخابز (El Safa Mills & Bakery Supplies)',
                'phone' => '01555667788',
                'email' => 'orders@elsafamills.com',
                'balance' => 6000.00,
            ],
        ];

        foreach ($suppliers as $sup) {
            Supplier::create($sup);
        }
    }
}
