<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

class ProductManufacturingService
{
    /**
     * Build a sorted, comma-separated key from an array of option IDs.
     *
     * @param  array<int, int>  $optionIds
     */
    public static function buildCombinationKey(array $optionIds): ?string
    {
        if (empty($optionIds)) {
            return null;
        }

        $sorted = collect($optionIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return implode(',', $sorted);
    }

    /**
     * Build a combination key from the variation carts of an order cart.
     *
     * @param  Collection<int, OrderVariationCart>|array  $variationCarts
     */
    public static function buildCombinationKeyFromVariationCarts($variationCarts): ?string
    {
        if (empty($variationCarts)) {
            return null;
        }

        $cartsCollection = is_array($variationCarts) ? collect($variationCarts) : $variationCarts;

        if ($cartsCollection->isEmpty()) {
            return null;
        }

        $allOptionIds = $cartsCollection->flatMap(
            fn ($vc) => is_array($vc->option_ids ?? null) ? $vc->option_ids : []
        )->all();

        return static::buildCombinationKey($allOptionIds);
    }

    /**
     * Get all possible option combinations for a product's variations.
     *
     * @return array<int, array<int, int>> Array of sorted option-ID arrays.
     */
    public static function getAllCombinations(Product $product): array
    {
        $product->loadMissing('variations.options');

        $variations = $product->variations->filter(fn ($v) => $v->options->isNotEmpty());

        if ($variations->isEmpty()) {
            return [];
        }

        $optionGroups = $variations->map(fn ($v) => $v->options->pluck('id')->all())->values()->all();

        return static::cartesianProduct($optionGroups);
    }

    /**
     * Compute the cartesian product of option groups.
     *
     * @param  array<int, array<int, int>>  $groups
     * @return array<int, array<int, int>>
     */
    private static function cartesianProduct(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        $result = [[]];

        foreach ($groups as $group) {
            $newResult = [];
            foreach ($result as $partial) {
                foreach ($group as $optionId) {
                    $newResult[] = array_merge($partial, [$optionId]);
                }
            }
            $result = $newResult;
        }

        // Sort each combination for consistent keys
        return array_map(function (array $combo) {
            sort($combo);

            return $combo;
        }, $result);
    }

    /**
     * Synchronize option-based manufacturing recipes for a product.
     *
     * @param  array<int, array{option_ids: array<int, int>, recipes: array<int, array<string, mixed>>}>  $optionRecipes
     */
    public static function syncOptionRecipesForProduct(Product $product, array $optionRecipes): void
    {
        // Delete existing combination-based specs for this product
        $existingSpecs = ProductManufacturing::where('product_id', $product->id)
            ->whereNotNull('option_combination_key')
            ->get();

        foreach ($existingSpecs as $spec) {
            $spec->productRecipeManufacturings()->delete();
            $spec->delete();
        }

        // Also remove the product-level (NULL key) spec since we're now using combinations
        $productLevelSpec = ProductManufacturing::where('product_id', $product->id)
            ->whereNull('option_combination_key')
            ->first();

        if ($productLevelSpec) {
            $productLevelSpec->productRecipeManufacturings()->delete();
            $productLevelSpec->delete();
        }

        foreach ($optionRecipes as $comboData) {
            $key = static::buildCombinationKey($comboData['option_ids'] ?? []);
            if (! $key || empty($comboData['recipes'])) {
                continue;
            }

            $spec = ProductManufacturing::create([
                'product_id' => $product->id,
                'option_combination_key' => $key,
            ]);

            foreach ($comboData['recipes'] as $item) {
                $spec->productRecipeManufacturings()->create([
                    'product_recipe_id' => $item['product_recipe_id'] ?? null,
                    'material_id' => $item['material_id'] ?? null,
                    'count' => $item['count'],
                    'unit_id' => $item['unit_id'] ?? null,
                ]);
            }
        }
    }

    /**
     * Synchronize product-level (no options) manufacturing recipes.
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
            ['product_id' => $product->id, 'option_combination_key' => null],
        );

        $spec->productRecipeManufacturings()->delete();

        foreach ($recipes as $item) {
            $spec->productRecipeManufacturings()->create([
                'product_recipe_id' => $item['product_recipe_id'] ?? null,
                'material_id' => $item['material_id'] ?? null,
                'count' => $item['count'],
                'unit_id' => $item['unit_id'] ?? null,
            ]);
        }

        return $spec;
    }

    /**
     * Delete all manufacturing specifications for a given product.
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
     * Find the best matching ProductManufacturing for a product + selected option combination.
     *
     * Falls back to the product-level (NULL key) spec if no combination-specific one exists.
     */
    public static function findSpecForProductAndOptions(int $productId, ?string $combinationKey): ?ProductManufacturing
    {
        // Try exact combination match first
        if ($combinationKey) {
            $spec = ProductManufacturing::with([
                'productRecipeManufacturings.material',
                'productRecipeManufacturings.productRecipe',
            ])
                ->where('product_id', $productId)
                ->where('option_combination_key', $combinationKey)
                ->latest('id')
                ->first();

            if ($spec) {
                return $spec;
            }
        }

        // Fallback to product-level spec (NULL key)
        return ProductManufacturing::with([
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ])
            ->where('product_id', $productId)
            ->whereNull('option_combination_key')
            ->latest('id')
            ->first();
    }

    /**
     * Check stock availability of recipe ingredients for a product in a branch.
     *
     * Returns null if stock is sufficient or no recipe exists.
     * Returns array with error payload if insufficient.
     *
     * @return array{status: false, message: string, insufficient_ingredient: array<string, mixed>}|null
     */
    public static function checkStockAvailability(
        int $productId,
        int $quantity,
        int $branchId,
        ?string $combinationKey = null
    ): ?array {
        $spec = static::findSpecForProductAndOptions($productId, $combinationKey);

        if (! $spec || $spec->productRecipeManufacturings->isEmpty()) {
            return null;
        }

        $requestedQty = max(1, $quantity);

        foreach ($spec->productRecipeManufacturings as $recipeItem) {
            $requiredQty = (float) $recipeItem->count * $requestedQty;
            if ($requiredQty <= 0) {
                continue;
            }

            if (! empty($recipeItem->material_id)) {
                $currentStock = (float) (MaterialStock::where('material_id', $recipeItem->material_id)
                    ->where('branch_id', $branchId)
                    ->value('stock') ?? 0);

                if ($currentStock < $requiredQty) {
                    $matName = $recipeItem->material?->name;
                    $name = is_array($matName)
                        ? ($matName['ar'] ?? $matName['en'] ?? "مادة خام #{$recipeItem->material_id}")
                        : ($matName ?? "مادة خام #{$recipeItem->material_id}");

                    return [
                        'status' => false,
                        'message' => "المخزون المتوفر للمادة الخام ({$name}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                        'insufficient_ingredient' => [
                            'type' => 'material',
                            'id' => $recipeItem->material_id,
                            'name' => $name,
                            'available_stock' => $currentStock,
                            'required_quantity' => $requiredQty,
                            'can_bypass' => true,
                        ],
                    ];
                }
            } elseif (! empty($recipeItem->product_recipe_id)) {
                $currentStock = (float) (ProductRecipeStock::where('product_recipe_id', $recipeItem->product_recipe_id)
                    ->where('branch_id', $branchId)
                    ->value('stock') ?? 0);

                if ($currentStock < $requiredQty) {
                    $recName = $recipeItem->productRecipe?->name;
                    $name = is_array($recName)
                        ? ($recName['ar'] ?? $recName['en'] ?? "وصفة #{$recipeItem->product_recipe_id}")
                        : ($recName ?? "وصفة #{$recipeItem->product_recipe_id}");

                    return [
                        'status' => false,
                        'message' => "المخزون المتوفر للوصفة ({$name}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                        'insufficient_ingredient' => [
                            'type' => 'product_recipe',
                            'id' => $recipeItem->product_recipe_id,
                            'name' => $name,
                            'available_stock' => $currentStock,
                            'required_quantity' => $requiredQty,
                            'can_bypass' => true,
                        ],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Deduct recipe ingredients from branch stock for a single OrderCart item.
     * Guarantees stock never drops below zero.
     */
    public static function deductStockForCartItem(OrderCart $cart, ?int $branchId = null): void
    {
        $branchId = $branchId ?? $cart->branch_id;
        if (! $branchId) {
            return;
        }

        $cart->loadMissing(['variationCarts']);
        $qty = max(1, (int) $cart->quantity);
        $combinationKey = static::buildCombinationKeyFromVariationCarts($cart->variationCarts);

        static::deductStockForProduct($cart->product_id, $qty, $branchId, $combinationKey);
    }

    /**
     * Deduct recipe ingredients from branch stock for a product, quantity, branch, and option combination.
     * Guarantees stock never drops below zero.
     */
    public static function deductStockForProduct(
        int $productId,
        int $quantity,
        int $branchId,
        ?string $combinationKey = null
    ): void {
        $spec = static::findSpecForProductAndOptions($productId, $combinationKey);
        if (! $spec || $spec->productRecipeManufacturings->isEmpty()) {
            return;
        }

        foreach ($spec->productRecipeManufacturings as $recipeItem) {
            $requiredQty = (float) $recipeItem->count * $quantity;
            if ($requiredQty <= 0) {
                continue;
            }

            if (! empty($recipeItem->material_id)) {
                $matStock = MaterialStock::where('material_id', $recipeItem->material_id)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if ($matStock) {
                    $current = (float) $matStock->stock;
                    // Stock must not drop below 0: if stock is 9 and recipe needs 10, deduct only 9
                    $deduct = min(max(0.0, $current), $requiredQty);
                    if ($deduct > 0) {
                        $matStock->decrement('stock', $deduct);
                    }
                }
            } elseif (! empty($recipeItem->product_recipe_id)) {
                $recStock = ProductRecipeStock::where('product_recipe_id', $recipeItem->product_recipe_id)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if ($recStock) {
                    $current = (float) $recStock->stock;
                    // Stock must not drop below 0: if stock is 9 and recipe needs 10, deduct only 9
                    $deduct = min(max(0.0, $current), $requiredQty);
                    if ($deduct > 0) {
                        $recStock->decrement('stock', $deduct);
                    }
                }
            }
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
            $relations[] = 'productManufacturings.productRecipeManufacturings.material';
            $relations[] = 'productManufacturings.productRecipeManufacturings.productRecipe';
            $relations[] = 'productManufacturings.productRecipeManufacturings.unit';
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
