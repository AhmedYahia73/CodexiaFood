<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [
            [
                'name' => ['ar' => 'خصم الافتتاح الكبير 10%', 'en' => 'Grand Opening 10% Discount'],
                'type' => 'percentage',
                'amount' => 10.00,
                'status' => true,
            ],
            [
                'name' => ['ar' => 'خصم الصيف 15%', 'en' => 'Summer Special 15% Discount'],
                'type' => 'percentage',
                'amount' => 15.00,
                'status' => true,
            ],
            [
                'name' => ['ar' => 'خصم العملاء المميزين 20 جنيه', 'en' => 'VIP Customer EGP 20 Discount'],
                'type' => 'value',
                'amount' => 20.00,
                'status' => true,
            ],
        ];

        foreach ($discounts as $discount) {
            Discount::create($discount);
        }
    }
}
