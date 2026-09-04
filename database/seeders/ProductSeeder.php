<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::where('type', 'product')->get();
        $tax = Tax::first();
        $discount = Discount::first();

        $appetizersCat = $categories->where('name.en', 'Appetizers & Salads')->first() ?? $categories->first();
        $mainDishesCat = $categories->where('name.en', 'Main Dishes & Grill')->first() ?? $categories->first();
        $burgersCat = $categories->where('name.en', 'Burgers & Sandwiches')->first() ?? $categories->first();
        $pizzaCat = $categories->where('name.en', 'Pizza & Pasta')->first() ?? $categories->first();
        $dessertsCat = $categories->where('name.en', 'Desserts & Sweets')->first() ?? $categories->first();
        $beveragesCat = $categories->where('name.en', 'Beverages & Juices')->first() ?? $categories->first();

        $products = [
            // Appetizers
            [
                'name' => ['ar' => 'سلطة سيزر بالدجاج', 'en' => 'Chicken Caesar Salad'],
                'description' => ['ar' => 'خس كابوتشا طازج، شرائح دجاج مشوي، جبن بارميزان ومحمّص', 'en' => 'Fresh romaine lettuce, grilled chicken slices, parmesan, and croutons'],
                'price' => 95.00,
                'image' => 'uploads/products/caesar_salad.jpg',
                'category_id' => $appetizersCat?->id,
            ],
            [
                'name' => ['ar' => 'أصابع الجبن الموزاريلا المقرمشة', 'en' => 'Crispy Mozzarella Sticks'],
                'description' => ['ar' => '6 قطع جبن موزاريلا مقلية تقدم مع صوص المارينارا', 'en' => '6 pieces fried mozzarella cheese served with marinara sauce'],
                'price' => 75.00,
                'image' => 'uploads/products/mozzarella_sticks.jpg',
                'category_id' => $appetizersCat?->id,
            ],

            // Main Dishes
            [
                'name' => ['ar' => 'وجبة كباب وكفتة مشوية', 'en' => 'Grilled Kebab & Kofta Combo'],
                'description' => ['ar' => 'مشويات مشكلة على الفحم تقدم مع أرز بسمتي وسلطات', 'en' => 'Charcoal grilled mixed platter served with basmati rice and dips'],
                'price' => 240.00,
                'image' => 'uploads/products/kebab_kofta.jpg',
                'category_id' => $mainDishesCat?->id,
            ],
            [
                'name' => ['ar' => 'وجبة نصف دجاجة مشوية', 'en' => 'Half Charcoal Grilled Chicken'],
                'description' => ['ar' => 'نصف دجاجة متبلة بخلطة الخصوصية مع البطاطس والخبز', 'en' => 'Marinated half chicken grilled on coals with fries and bread'],
                'price' => 170.00,
                'image' => 'uploads/products/half_chicken.jpg',
                'category_id' => $mainDishesCat?->id,
            ],

            // Burgers
            [
                'name' => ['ar' => 'برجر كلاسيك لحم بلدي', 'en' => 'Classic Beef Burger'],
                'description' => ['ar' => 'شريحة لحم بلدي 200 جرام، جبن شيدر، بصل مكرمل وخس', 'en' => '200g beef patty, cheddar cheese, caramelized onions, and lettuce'],
                'price' => 140.00,
                'image' => 'uploads/products/classic_burger.jpg',
                'category_id' => $burgersCat?->id,
            ],
            [
                'name' => ['ar' => 'ساندوتش زانجر دجاج مقرمش', 'en' => 'Crispy Chicken Zinger'],
                'description' => ['ar' => 'صدر دجاج مقرمش سبايسي، جبن، مايونيز وخس', 'en' => 'Spicy crispy chicken breast, cheese, mayonnaise, and lettuce'],
                'price' => 125.00,
                'image' => 'uploads/products/zinger.jpg',
                'category_id' => $burgersCat?->id,
            ],

            // Pizza
            [
                'name' => ['ar' => 'بيتزا مارجريتا إيطالي', 'en' => 'Italian Margherita Pizza'],
                'description' => ['ar' => 'صلصة طماطم إيطالية، جبن موزاريلا، ريحان طازج وزيت زيتون', 'en' => 'Italian tomato sauce, mozzarella cheese, fresh basil, and olive oil'],
                'price' => 110.00,
                'image' => 'uploads/products/margherita.jpg',
                'category_id' => $pizzaCat?->id,
            ],
            [
                'name' => ['ar' => 'بيتزا بيبروني وعشاق اللحوم', 'en' => 'Meat Lovers Pepperoni Pizza'],
                'description' => ['ar' => 'شرائح البيبروني، سوسيس، مفروم وجبن موزاريلا وفير', 'en' => 'Pepperoni slices, sausage, minced beef, and extra mozzarella'],
                'price' => 165.00,
                'image' => 'uploads/products/pepperoni.jpg',
                'category_id' => $pizzaCat?->id,
            ],

            // Desserts
            [
                'name' => ['ar' => 'مولتن كيك بالشوكولاتة', 'en' => 'Chocolate Lava Molten Cake'],
                'description' => ['ar' => 'كيك الشوكولاتة الساخن بالحشوة الذائبة مع بولات آيس كريم فانيليا', 'en' => 'Hot chocolate cake with melting core served with vanilla ice cream'],
                'price' => 80.00,
                'image' => 'uploads/products/molten_cake.jpg',
                'category_id' => $dessertsCat?->id,
            ],

            // Beverages
            [
                'name' => ['ar' => 'عصير مانجو طازج', 'en' => 'Fresh Mango Juice'],
                'description' => ['ar' => 'عصير مانجو طبيعي 100% بدون إضافات', 'en' => '100% natural fresh mango juice'],
                'price' => 45.00,
                'image' => 'uploads/products/mango_juice.jpg',
                'category_id' => $beveragesCat?->id,
            ],
            [
                'name' => ['ar' => 'موهيتو ليمون ونعناع', 'en' => 'Lemon Mint Mojito'],
                'description' => ['ar' => 'مشروب منعش بالليمون والنعناع والثلج المجروش', 'en' => 'Refreshing lemon, mint, and crushed ice mocktail'],
                'price' => 50.00,
                'image' => 'uploads/products/mojito.jpg',
                'category_id' => $beveragesCat?->id,
            ],
        ];

        foreach ($products as $p) {
            Product::create([
                'name' => $p['name'],
                'description' => $p['description'],
                'price' => $p['price'],
                'image' => $p['image'],
                'tax_id' => $tax?->id,
                'discount_id' => $discount?->id,
                'category_id' => $p['category_id'],
            ]);
        }
    }
}
