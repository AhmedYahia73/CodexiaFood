<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => ['ar' => 'نقداً (كاش)', 'en' => 'Cash'],
                'description' => ['ar' => 'الدفع النادي عند الاستلام أو بالفرع', 'en' => 'Cash payment on delivery or in-store'],
                'icon' => 'heroicon-o-banknotes',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'بطاقة ائتمان (فيزا / ماستركارد)', 'en' => 'Credit / Debit Card'],
                'description' => ['ar' => 'الدفع الإلكتروني عبر الماكينة', 'en' => 'Electronic POS payment'],
                'icon' => 'heroicon-o-credit-card',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'محفظة فودافون كاش', 'en' => 'Vodafone Cash Wallet'],
                'description' => ['ar' => 'الدفع عبر المحفظة الإلكترونية', 'en' => 'Mobile wallet transfer'],
                'icon' => 'heroicon-o-device-phone-mobile',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'آبل باي / جوجل باي', 'en' => 'Apple Pay / Google Pay'],
                'description' => ['ar' => 'الدفع اللاتلامسي الذكي', 'en' => 'Contactless NFC payment'],
                'icon' => 'heroicon-o-device-tablet',
                'status' => true,
            ],
        ];

        foreach ($methods as $pm) {
            PaymentMethod::create($pm);
        }
    }
}
