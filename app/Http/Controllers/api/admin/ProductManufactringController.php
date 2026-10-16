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
    public function selectOptions(Request $request): JsonResponse
    {
        $type = $request->get('type');

        $data = [];
        if ($type === 'finished' || $type === 'product') {
            $data['products'] = Product::select('id', 'name')->get();
            $data['product_recipes'] = ProductRecipe::select('id', 'name')->get();
        } elseif ($type === 'unfinished' || $type === 'recipe') {
            $data['product_recipes'] = ProductRecipe::select('id', 'name')->get();
            $data['materials'] = Material::select('id', 'name')->get();
        } else {
            $data['products'] = Product::select('id', 'name')->get();
            $data['product_recipes'] = ProductRecipe::select('id', 'name')->get();
            $data['materials'] = Material::select('id', 'name')->get();
        }

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Current page number.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',

            /**
             * Filter by manufacturing specification type: finished (product_id is not null) or unfinished (product_recipe_id is not null).
             *
             * @var string|null
             *
             * @example "finished"
             */
            'type' => 'sometimes|nullable|string|in:finished,unfinished,product,recipe,all',
        ]);

        $query = ProductManufacturing::with([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ]);

        $type = $validated['type'] ?? $request->get('type');
        if ($type === 'finished' || $type === 'product') {
            $query->whereNotNull('product_id');
        } elseif ($type === 'unfinished' || $type === 'recipe') {
            $query->whereNotNull('product_recipe_id');
        }

        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));
        $specs = $query->latest()->paginate($perPage);

        return ProductManufacturingResource::collection($specs)->additional([
            'counts' => [
                'all' => ProductManufacturing::count(),
                'finished' => ProductManufacturing::whereNotNull('product_id')->count(),
                'unfinished' => ProductManufacturing::whereNotNull('product_recipe_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $isFinished = $request->filled('product_id');

        if ($isFinished) {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'recipes' => 'required|array|min:1',
                'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
                'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
                'recipes.*.count' => 'required|integer|min:1',
            ]);
        } else {
            $validated = $request->validate([
                'product_recipe_id' => 'required|exists:product_recipes,id',
                'recipes' => 'required|array|min:1',
                'recipes.*.material_id' => 'required|exists:materials,id',
                'recipes.*.count' => 'required|integer|min:1',
            ]);
        }

        $productManufacturing = DB::transaction(function () use ($validated, $isFinished) {
            $spec = ProductManufacturing::create([
                'product_id' => $isFinished ? $validated['product_id'] : null,
                'product_recipe_id' => ! $isFinished ? $validated['product_recipe_id'] : null,
            ]);

            foreach ($validated['recipes'] as $recipe) {
                $spec->productRecipeManufacturings()->create([
                    'product_recipe_id' => $recipe['product_recipe_id'] ?? null,
                    'material_id' => $recipe['material_id'] ?? null,
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
        ]);
    }

    public function update(Request $request, ProductManufacturing $productManufacturing): JsonResponse
    {
        $isFinished = $request->filled('product_id') || ($productManufacturing->product_id && ! $request->filled('product_recipe_id'));

        if ($isFinished) {
            $validated = $request->validate([
                'product_id' => 'sometimes|nullable|exists:products,id',
                'recipes' => 'sometimes|required|array|min:1',
                'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
                'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
                'recipes.*.count' => 'required|integer|min:1',
            ]);
        } else {
            $validated = $request->validate([
                'product_recipe_id' => 'sometimes|nullable|exists:product_recipes,id',
                'recipes' => 'sometimes|required|array|min:1',
                'recipes.*.material_id' => 'required|exists:materials,id',
                'recipes.*.count' => 'required|integer|min:1',
            ]);
        }

        DB::transaction(function () use ($productManufacturing, $validated, $isFinished) {
            $productManufacturing->update([
                'product_id' => $isFinished ? ($validated['product_id'] ?? $productManufacturing->product_id) : null,
                'product_recipe_id' => ! $isFinished ? ($validated['product_recipe_id'] ?? $productManufacturing->product_recipe_id) : null,
            ]);

            if (isset($validated['recipes'])) {
                $productManufacturing->productRecipeManufacturings()->delete();

                foreach ($validated['recipes'] as $recipe) {
                    $productManufacturing->productRecipeManufacturings()->create([
                        'product_recipe_id' => $recipe['product_recipe_id'] ?? null,
                        'material_id' => $recipe['material_id'] ?? null,
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
