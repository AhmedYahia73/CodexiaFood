<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            [
                'name' => ['ar' => 'ضريبة القيمة المضافة (VAT)', 'en' => 'Value Added Tax (VAT)'],
                'type' => 'percentage',
                'amount' => 14.00,
                'status' => true,
            ],
            [
                'name' => ['ar' => 'رسوم الخدمة (Service Charge)', 'en' => 'Service Charge Tax'],
                'type' => 'percentage',
                'amount' => 12.00,
                'status' => true,
            ],
            [
                'name' => ['ar' => 'ضريبة بلدية ثابتة', 'en' => 'Fixed Municipal Tax'],
                'type' => 'value',
                'amount' => 10.00,
                'status' => true,
            ],
        ];

        foreach ($taxes as $tax) {
            Tax::create($tax);
        }
    }
}
