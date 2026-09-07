<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductManufacturingResource;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductManufactringController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'products' => Product::select('id', 'name')->get(),
                'product_recipes' => ProductRecipe::select('id', 'name')->get(),
                'materials' => Material::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $specs = ProductManufacturing::with([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ])->latest()->paginate($request->get('per_page', 15));

        return ProductManufacturingResource::collection($specs)->additional([
            'select_options' => [
                'products' => Product::select('id', 'name')->get(),
                'product_recipes' => ProductRecipe::select('id', 'name')->get(),
                'materials' => Material::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id|required_without:product_recipe_id',
            'product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:product_id',
            'recipes' => 'required|array|min:1',
            'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
            'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
            'recipes.*.count' => 'required|integer|min:1',
        ]);

        $productManufacturing = DB::transaction(function () use ($validated) {
            $spec = ProductManufacturing::create([
                'product_id' => $validated['product_id'] ?? null,
                'product_recipe_id' => $validated['product_recipe_id'] ?? null,
            ]);

            foreach ($validated['recipes'] as $recipe) {
                $spec->productRecipeManufacturings()->create([
                    'material_id' => $recipe['material_id'] ?? null,
                    'product_recipe_id' => $recipe['product_recipe_id'] ?? null,
                    'count' => $recipe['count'],
                ]);
            }

            return $spec;
        });

        $productManufacturing->load([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product manufacturing specification created successfully.',
            'data' => new ProductManufacturingResource($productManufacturing),
            'select_options' => [
                'products' => Product::select('id', 'name')->get(),
                'product_recipes' => ProductRecipe::select('id', 'name')->get(),
                'materials' => Material::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(ProductManufacturing $productManufacturing): JsonResponse
    {
        $productManufacturing->load([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ]);

        return response()->json([
            'status' => true,
            'data' => new ProductManufacturingResource($productManufacturing),
            'select_options' => [
                'products' => Product::select('id', 'name')->get(),
                'product_recipes' => ProductRecipe::select('id', 'name')->get(),
                'materials' => Material::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, ProductManufacturing $productManufacturing): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_recipe_id' => 'nullable|exists:product_recipes,id',
            'recipes' => 'sometimes|required|array|min:1',
            'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
            'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
            'recipes.*.count' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($productManufacturing, $validated) {
            $productManufacturing->update([
                'product_id' => array_key_exists('product_id', $validated) ? $validated['product_id'] : $productManufacturing->product_id,
                'product_recipe_id' => array_key_exists('product_recipe_id', $validated) ? $validated['product_recipe_id'] : $productManufacturing->product_recipe_id,
            ]);

            if (isset($validated['recipes'])) {
                $productManufacturing->productRecipeManufacturings()->delete();

                foreach ($validated['recipes'] as $recipe) {
                    $productManufacturing->productRecipeManufacturings()->create([
                        'material_id' => $recipe['material_id'] ?? null,
                        'product_recipe_id' => $recipe['product_recipe_id'] ?? null,
                        'count' => $recipe['count'],
                    ]);
                }
            }
        });

        $productManufacturing->load([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product manufacturing specification updated successfully.',
            'data' => new ProductManufacturingResource($productManufacturing),
            'select_options' => [
                'products' => Product::select('id', 'name')->get(),
                'product_recipes' => ProductRecipe::select('id', 'name')->get(),
                'materials' => Material::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(ProductManufacturing $productManufacturing): JsonResponse
    {
        DB::transaction(function () use ($productManufacturing) {
            $productManufacturing->productRecipeManufacturings()->delete();
            $productManufacturing->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Product manufacturing specification deleted successfully.',
        ]);
    }
}
