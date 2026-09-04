<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Discount;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        $tax = Tax::first();
        $discount = Discount::first();

        $addons = [
            [
                'name' => ['ar' => 'شريحة جبن شيدر إضافية', 'en' => 'Extra Cheddar Cheese Slice'],
                'image' => 'uploads/addons/extra_cheddar.jpg',
                'price' => 15.00,
            ],
            [
                'name' => ['ar' => 'إضافة صوص التومية السوري', 'en' => 'Extra Garlic Dip Sauce'],
                'image' => 'uploads/addons/garlic_dip.jpg',
                'price' => 10.00,
            ],
            [
                'name' => ['ar' => 'إضافة صوص باربيكيو مدخن', 'en' => 'Smoked BBQ Sauce'],
                'image' => 'uploads/addons/bbq_sauce.jpg',
                'price' => 12.00,
            ],
            [
                'name' => ['ar' => 'باكيت بطاطس مقلية كيرلي', 'en' => 'Curly French Fries Side'],
                'image' => 'uploads/addons/curly_fries.jpg',
                'price' => 30.00,
            ],
            [
                'name' => ['ar' => 'إضافة هالابينو حار', 'en' => 'Sliced Jalapeno Peppers'],
                'image' => 'uploads/addons/jalapeno.jpg',
                'price' => 10.00,
            ],
        ];

        foreach ($addons as $addon) {
            Addon::create([
                'name' => $addon['name'],
                'image' => $addon['image'],
                'price' => $addon['price'],
                'tax_id' => $tax?->id,
                'discount_id' => $discount?->id,
            ]);
        }
    }
}
