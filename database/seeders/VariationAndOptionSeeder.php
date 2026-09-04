<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Database\Seeder;

class VariationAndOptionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        $burger = $products->where('name.en', 'Classic Beef Burger')->first() ?? $products->first();
        $pizza = $products->where('name.en', 'Italian Margherita Pizza')->first() ?? $products->first();

        if ($burger) {
            // Variation 1: Combo Size
            $v1 = Variation::create([
                'name' => ['ar' => 'حجم الوجبة', 'en' => 'Meal Size'],
                'status' => true,
                'required' => true,
                'product_id' => $burger->id,
            ]);

            Option::create([
                'name' => ['ar' => 'ساندوتش فقط (Single)', 'en' => 'Single Sandwich Only'],
                'price' => 0.00,
                'status' => true,
                'product_id' => $burger->id,
                'variation_id' => $v1->id,
            ]);

            Option::create([
                'name' => ['ar' => 'وجبة كومبو (بطاطس ومشروب)', 'en' => 'Combo Meal (Fries & Drink)'],
                'price' => 35.00,
                'status' => true,
                'product_id' => $burger->id,
                'variation_id' => $v1->id,
            ]);

            // Variation 2: Spicy Level
            $v2 = Variation::create([
                'name' => ['ar' => 'درجة الشطة', 'en' => 'Spice Level'],
                'status' => true,
                'required' => false,
                'product_id' => $burger->id,
            ]);

            Option::create([
                'name' => ['ar' => 'عادي (Regular)', 'en' => 'Regular'],
                'price' => 0.00,
                'status' => true,
                'product_id' => $burger->id,
                'variation_id' => $v2->id,
            ]);

            Option::create([
                'name' => ['ar' => 'حار (Spicy Hot)', 'en' => 'Spicy Hot'],
                'price' => 0.00,
                'status' => true,
                'product_id' => $burger->id,
                'variation_id' => $v2->id,
            ]);
        }

        if ($pizza) {
            $vp = Variation::create([
                'name' => ['ar' => 'حجم البيتزا', 'en' => 'Pizza Size'],
                'status' => true,
                'required' => true,
                'product_id' => $pizza->id,
            ]);

            Option::create([
                'name' => ['ar' => 'صغير (Small 22cm)', 'en' => 'Small (22cm)'],
                'price' => 0.00,
                'status' => true,
                'product_id' => $pizza->id,
                'variation_id' => $vp->id,
            ]);

            Option::create([
                'name' => ['ar' => 'وسط (Medium 28cm)', 'en' => 'Medium (28cm)'],
                'price' => 40.00,
                'status' => true,
                'product_id' => $pizza->id,
                'variation_id' => $vp->id,
            ]);

            Option::create([
                'name' => ['ar' => 'كبير (Large 35cm)', 'en' => 'Large (35cm)'],
                'price' => 75.00,
                'status' => true,
                'product_id' => $pizza->id,
                'variation_id' => $vp->id,
            ]);
        }
    }
}
