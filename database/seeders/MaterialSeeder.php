<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materialCategory = Category::where('type', 'material')->first();

        $materials = [
            ['name' => ['ar' => 'لحم بقر طازج m1', 'en' => 'Fresh Beef Meat'], 'stock' => 250],
            ['name' => ['ar' => 'صدور دجاج مخلاة m2', 'en' => 'Boneless Chicken Breast'], 'stock' => 180],
            ['name' => ['ar' => 'جبنة موزاريللا مبروشة m3', 'en' => 'Shredded Mozzarella Cheese'], 'stock' => 120],
            ['name' => ['ar' => 'دقيق أبيض فاخر m4', 'en' => 'Premium White Flour'], 'stock' => 500],
            ['name' => ['ar' => 'طماطم طازجة m5', 'en' => 'Fresh Tomatoes'], 'stock' => 300],
            ['name' => ['ar' => 'بصل أحمر m6', 'en' => 'Red Onions'], 'stock' => 200],
            ['name' => ['ar' => 'زيت زيتون بكر m7', 'en' => 'Virgin Olive Oil'], 'stock' => 80],
            ['name' => ['ar' => 'بطاطس للتقلية m8', 'en' => 'Frying Potatoes'], 'stock' => 450],
            ['name' => ['ar' => 'صلصة طماطم مركزة m9', 'en' => 'Concentrated Tomato Paste'], 'stock' => 150],
            ['name' => ['ar' => 'خس كابوتشا m10', 'en' => 'Iceberg Lettuce'], 'stock' => 90],
        ];

        foreach ($materials as $mat) {
            Material::create([
                'name' => $mat['name'],
                'stock' => $mat['stock'],
                'status' => true,
                'category_id' => $materialCategory?->id,
            ]);
        }
    }
}
