<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\ProductRecipe;
use App\Models\Waste;
use Illuminate\Database\Seeder;

class WasteSeeder extends Seeder
{
    public function run(): void
    {
        $materials = Material::all();
        $recipes = ProductRecipe::all();

        foreach ($materials as $mat) {
            Waste::create([
                'product_recipe_id' => $recipes->random()->id,
                'material_id' => $mat->id,
                'count' => rand(1, 10),
            ]);
        }
    }
}
