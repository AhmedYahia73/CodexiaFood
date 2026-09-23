<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManufacturingListResource;
use App\Http\Resources\ProductManufacturingResource;
use App\Models\Branch;
use App\Models\ManufacturingList;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ManufactringController extends Controller
{
    public function selectOptions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptionsData($request),
        ]);
    }

    public function getSpecification(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id|required_without:product_recipe_id',
            'product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:product_id',
        ]);

        $query = ProductManufacturing::query();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        } else {
            $query->where('product_recipe_id', $request->product_recipe_id);
        }

        $spec = $query->with([
            'product',
            'productRecipe',
            'productRecipeManufacturings.material',
            'productRecipeManufacturings.productRecipe',
        ])->first();

        if (! $spec) {
            return response()->json([
                'status' => false,
                'message' => 'No standard manufacturing specification found for the selected item.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => new ProductManufacturingResource($spec),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ManufacturingList::with([
            'branch',
            'product',
            'productRecipe',
            'manufacturingRecipes.material',
            'manufacturingRecipes.productRecipe',
        ])->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $lists = $query->paginate($request->get('per_page', 15));

        return ManufacturingListResource::collection($lists)->additional([
            'select_options' => $this->getSelectOptionsData($request),
        ]);
    }

    public function manufacture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'product_id' => 'nullable|exists:products,id|required_without:product_recipe_id',
            'product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:product_id',
            'count' => 'required|integer|min:1',
            'recipes' => 'required|array|min:1',
            'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
            'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
            'recipes.*.count' => 'required|integer|min:1',
        ]);

        $branchId = (int) $validated['branch_id'];

        // 1. Stock availability validation in the designated branch
        $materialStocks = [];
        $recipeStocks = [];

        foreach ($validated['recipes'] as $item) {
            if (! empty($item['material_id'])) {
                $matStock = MaterialStock::where('material_id', $item['material_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($matStock?->stock ?? 0);
                if ($availableStock < $item['count']) {
                    $material = Material::find($item['material_id']);
                    $name = is_array($material?->name)
                        ? ($material->name['en'] ?? $material->name['ar'] ?? $item['material_id'])
                        : ($material?->name ?? $item['material_id']);

                    return response()->json([
                        'status' => false,
                        'message' => "Insufficient stock in this branch for material '{$name}'. Available: {$availableStock}, Required: {$item['count']}.",
                    ], 422);
                }
                $materialStocks[] = ['stockModel' => $matStock, 'count' => $item['count']];
            } elseif (! empty($item['product_recipe_id'])) {
                $recStock = ProductRecipeStock::where('product_recipe_id', $item['product_recipe_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($recStock?->stock ?? 0);
                if ($availableStock < $item['count']) {
                    $recipe = ProductRecipe::find($item['product_recipe_id']);
                    $name = is_array($recipe?->name)
                        ? ($recipe->name['en'] ?? $recipe->name['ar'] ?? $item['product_recipe_id'])
                        : ($recipe?->name ?? $item['product_recipe_id']);

                    return response()->json([
                        'status' => false,
                        'message' => "Insufficient stock in this branch for product recipe '{$name}'. Available: {$availableStock}, Required: {$item['count']}.",
                    ], 422);
                }
                $recipeStocks[] = ['stockModel' => $recStock, 'count' => $item['count']];
            }
        }

        // 2. Perform manufacturing execution in DB transaction
        $manufacturingList = DB::transaction(function () use ($validated, $branchId, $materialStocks, $recipeStocks) {
            // Deduct ingredients stock from the designated branch
            foreach ($materialStocks as $matItem) {
                $matItem['stockModel']->decrement('stock', $matItem['count']);
            }

            foreach ($recipeStocks as $recItem) {
                $recItem['stockModel']->decrement('stock', $recItem['count']);
            }

            // Increment manufactured item stock (only product recipes track stock by branch; products are on-demand)
            if (! empty($validated['product_recipe_id'])) {
                $targetStock = ProductRecipeStock::firstOrCreate(
                    ['product_recipe_id' => $validated['product_recipe_id'], 'branch_id' => $branchId],
                    ['stock' => 0]
                );
                $targetStock->increment('stock', $validated['count']);
            }

            // Create ManufacturingList
            $mList = ManufacturingList::create([
                'branch_id' => $branchId,
                'product_id' => $validated['product_id'] ?? null,
                'product_recipe_id' => $validated['product_recipe_id'] ?? null,
                'count' => $validated['count'],
            ]);

            // Create ManufacturingRecipe items
            foreach ($validated['recipes'] as $recipe) {
                $mList->manufacturingRecipes()->create([
                    'material_id' => $recipe['material_id'] ?? null,
                    'product_recipe_id' => $recipe['product_recipe_id'] ?? null,
                    'count' => $recipe['count'],
                ]);
            }

            return $mList;
        });

        $manufacturingList->load([
            'branch',
            'product',
            'productRecipe',
            'manufacturingRecipes.material',
            'manufacturingRecipes.productRecipe',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Manufacturing process executed successfully.',
            'data' => new ManufacturingListResource($manufacturingList),
        ], 201);
    }

    public function show(ManufacturingList $manufacturingList): JsonResponse
    {
        $manufacturingList->load([
            'branch',
            'product',
            'productRecipe',
            'manufacturingRecipes.material',
            'manufacturingRecipes.productRecipe',
        ]);

        return response()->json([
            'status' => true,
            'data' => new ManufacturingListResource($manufacturingList),
        ]);
    }

    private function getSelectOptionsData(?Request $request = null): array
    {
        $branchId = $request?->query('branch_id');

        return [
            'branches' => Branch::select('id', 'name')->get(),
            'products' => Product::select('id', 'name')->get(),
            'product_recipes' => ProductRecipe::select('id', 'name')->get()->map(function ($item) use ($branchId) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'stock' => $branchId ? (float) $item->stockForBranch((int) $branchId) : (float) $item->totalStock(),
                ];
            }),
            'materials' => Material::select('id', 'name')->get()->map(function ($item) use ($branchId) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'stock' => $branchId ? (float) $item->stockForBranch((int) $branchId) : (float) $item->totalStock(),
                ];
            }),
        ];
    }
}
