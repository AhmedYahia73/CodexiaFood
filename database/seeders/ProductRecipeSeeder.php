<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeManufacturing;
use Illuminate\Database\Seeder;

class ProductRecipeSeeder extends Seeder
{
    public function run(): void
    {
        $recipeCategory = Category::where('type', 'recipe')->first();
        $materials = Material::all();
        $products = Product::all();

        $recipesData = [
            ['name' => ['ar' => 'وصفة عجينة البيتزا الإيطالية', 'en' => 'Italian Pizza Dough Recipe'], 'stock' => 100],
            ['name' => ['ar' => 'وصفة خلطة البرجر الخاصة', 'en' => 'Special Burger Patty Recipe'], 'stock' => 150],
            ['name' => ['ar' => 'وصفة تتبيلة المشويات والكبسة', 'en' => 'Grilled Meat Seasoning Recipe'], 'stock' => 80],
            ['name' => ['ar' => 'وصفة خلطة الزانجر السبايسي', 'en' => 'Spicy Zinger Breading Recipe'], 'stock' => 120],
        ];

        foreach ($recipesData as $rData) {
            $recipe = ProductRecipe::create([
                'name' => $rData['name'],
                'status' => true,
                'stock' => $rData['stock'],
                'category_id' => $recipeCategory?->id,
            ]);

            // Link to Materials
            foreach ($materials->take(2) as $material) {
                ProductRecipeManufacturing::create([
                    'product_recipe_id' => $recipe->id,
                    'material_id' => $material->id,
                    'count' => rand(2, 5),
                ]);
            }

            // Link to Products
            foreach ($products->take(2) as $product) {
                ProductManufacturing::create([
                    'product_recipe_id' => $recipe->id,
                    'product_id' => $product->id,
                    'count' => 1,
                ]);
            }
        }
    }
}
