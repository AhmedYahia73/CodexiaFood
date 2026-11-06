<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\Tax;
use App\Models\Unit;

class ProductManufacturingService
{
    /**
     * Synchronize manufacturing ingredients / recipes for a given product.
     *
     * @param  array<int, array<string, mixed>>|null  $recipes
     */
    public static function syncForProduct(Product $product, ?array $recipes): ?ProductManufacturing
    {
        if ($recipes === null) {
            return $product->productManufacturing;
        }

        if (empty($recipes)) {
            static::deleteForProduct($product);

            return null;
        }

        /** @var ProductManufacturing $spec */
        $spec = ProductManufacturing::firstOrCreate(
            ['product_id' => $product->id],
            ['product_recipe_id' => null]
        );

        $spec->productRecipeManufacturings()->delete();

        foreach ($recipes as $item) {
            $spec->productRecipeManufacturings()->create([
                'product_recipe_id' => $item['product_recipe_id'] ?? null,
                'material_id' => $item['material_id'] ?? null,
                'count' => $item['count'],
                'unit_id' => $item['unit_id'],
            ]);
        }

        return $spec;
    }

    /**
     * Delete manufacturing specification for a given product.
     */
    public static function deleteForProduct(Product $product): void
    {
        $specs = $product->productManufacturings()->get();

        foreach ($specs as $spec) {
            $spec->productRecipeManufacturings()->delete();
            $spec->delete();
        }
    }

    /**
     * Build eager loading relations for products.
     *
     * @return array<int, string>
     */
    public static function eagerLoadRelations(bool $includeRecipes = false): array
    {
        $relations = [
            'tax',
            'discount',
            'category',
            'subCategory',
            'variations.options',
        ];

        if ($includeRecipes) {
            $relations[] = 'productManufacturing.productRecipeManufacturings.material';
            $relations[] = 'productManufacturing.productRecipeManufacturings.productRecipe';
            $relations[] = 'productManufacturing.productRecipeManufacturings.unit';
        }

        return $relations;
    }

    /**
     * Shared select options for product forms.
     *
     * @return array<string, mixed>
     */
    public static function getSelectOptions(): array
    {
        return [
            'tax' => Tax::select('id', 'name', 'type', 'amount')->get(),
            'discount' => Discount::select('id', 'name', 'type', 'amount')->get(),
            'parent_categories' => Category::whereNull('category_id')->select('id', 'name', 'type')->get(),
            'sub_categories' => Category::whereNotNull('category_id')->select('id', 'name', 'category_id', 'type')->get(),
            'materials' => Material::select('id', 'name')->get(),
            'product_recipes' => ProductRecipe::select('id', 'name')->get(),
            'units' => Unit::select('id', 'name', 'value')->get(),
        ];
    }
}
