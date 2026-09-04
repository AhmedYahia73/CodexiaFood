<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => ['ar' => 'المقبلات والسلطات', 'en' => 'Appetizers & Salads'],
                'description' => ['ar' => 'أشهى السلطات الطازجة والمقبلات الساخنة والباردة', 'en' => 'Fresh salads and hot & cold appetizers'],
                'type' => 'product',
                'image' => 'uploads/categories/appetizers.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'الوجبات الرئيسية والمشويات', 'en' => 'Main Dishes & Grill'],
                'description' => ['ar' => 'وجبات اللحوم والدواجن المشوية على الفحم', 'en' => 'Charcoal grilled meat and chicken main meals'],
                'type' => 'product',
                'image' => 'uploads/categories/main_dishes.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'البرجر والساندوتشات', 'en' => 'Burgers & Sandwiches'],
                'description' => ['ar' => 'برجر اللحم البلدي والدجاج المقرمش مع الجبن', 'en' => 'Fresh beef burger patties and crispy chicken sandwiches'],
                'type' => 'product',
                'image' => 'uploads/categories/burgers.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'البيتزا والباستا', 'en' => 'Pizza & Pasta'],
                'description' => ['ar' => 'بيتزا إيطالية بعجينة طازجة وصوص إيطالي أصلي', 'en' => 'Italian pizza with fresh dough and original sauces'],
                'type' => 'product',
                'image' => 'uploads/categories/pizza.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'الحلويات الشرقية والغربية', 'en' => 'Desserts & Sweets'],
                'description' => ['ar' => 'أشهى أنواع الكيك والآيس كريم والحلويات', 'en' => 'Delicious cakes, ice cream, and traditional desserts'],
                'type' => 'product',
                'image' => 'uploads/categories/desserts.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'المشروبات والعصائر', 'en' => 'Beverages & Juices'],
                'description' => ['ar' => 'عصائر طازجة، مشروبات غازية ومشروبات ساخنة', 'en' => 'Fresh fruit juices, sodas, and hot coffee/tea'],
                'type' => 'product',
                'image' => 'uploads/categories/beverages.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'مواد خام المطبخ', 'en' => 'Kitchen Raw Materials'],
                'description' => ['ar' => 'المكونات الأساسية لتجهيز الوجبات', 'en' => 'Primary kitchen ingredients and supplies'],
                'type' => 'material',
                'image' => 'uploads/categories/materials.jpg',
                'status' => true,
            ],
            [
                'name' => ['ar' => 'وصفات التحضير والتصنيع', 'en' => 'Manufacturing Recipes'],
                'description' => ['ar' => 'وصفات العجائن والمخللات والتتبيلات', 'en' => 'Dough, sauces, and marinade recipes'],
                'type' => 'recipe',
                'image' => 'uploads/categories/recipes.jpg',
                'status' => true,
            ],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }
}
