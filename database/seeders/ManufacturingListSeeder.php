<?php

namespace Database\Seeders;

use App\Models\ManufacturingList;
use App\Models\ManufacturingRecipe;
use App\Models\Material;
use App\Models\ProductRecipe;
use Illuminate\Database\Seeder;

class ManufacturingListSeeder extends Seeder
{
    public function run(): void
    {
        $materials = Material::all();
        $recipes = ProductRecipe::all();

        foreach ($recipes as $recipe) {
            $mList = ManufacturingList::create([
                'material_id' => $materials->random()->id,
                'product_recipe_id' => $recipe->id,
                'count' => rand(10, 50),
            ]);

            ManufacturingRecipe::create([
                'material_id' => $materials->random()->id,
                'product_recipe_id' => $recipe->id,
                'count' => rand(1, 5),
                'manufacturing_list_id' => $mList->id,
            ]);
        }
    }
}
