<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\InventoryDetailResource;
use App\Http\Resources\InventoryListResource;
use App\Http\Resources\InventoryProductRecipeResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryProductRecipe;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryRecipeController extends BaseBranchController
{
    /**
     * Get select options for recipe inventory (current branch).
     */
    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    /**
     * Create a new recipe inventory and populate all product recipes with current branch stock.
     */
    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $inventory = DB::transaction(function () use ($validated, $branchId): Inventory {
            $inventory = Inventory::create([
                'name' => $validated['name'],
                'branch_id' => $branchId,
                'status' => 'pending',
            ]);

            $recipes = ProductRecipe::all();

            foreach ($recipes as $recipe) {
                $stock = (float) (ProductRecipeStock::where('product_recipe_id', $recipe->id)
                    ->where('branch_id', $branchId)
                    ->value('stock') ?? 0);

                InventoryProductRecipe::create([
                    'inventory_id' => $inventory->id,
                    'product_recipe_id' => $recipe->id,
                    'stock' => $stock,
                    'actual_stock' => $stock,
                ]);
            }

            return $inventory;
        });

        $inventory->load(['branch', 'inventoryProductRecipes.productRecipe']);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء جرد وصفات المنتجات بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ], 201);
    }

    /**
     * List inventories with status 'pending' that have recipe items for this branch.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $inventories = Inventory::where('branch_id', $branchId)
            ->where('status', 'pending')
            ->whereHas('inventoryProductRecipes')
            ->with('branch')
            ->latest('id')
            ->paginate($perPage);

        return InventoryListResource::collection($inventories);
    }

    /**
     * List inventories with status != 'pending' (approved or rejected) for this branch.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:approve,reject',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $query = Inventory::where('branch_id', $branchId)
            ->where('status', '!=', 'pending')
            ->whereHas('inventoryProductRecipes')
            ->with('branch')
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $inventories = $query->paginate($perPage);

        return InventoryListResource::collection($inventories);
    }

    /**
     * Show recipe inventory details and its recipe items.
     */
    public function show(Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);
        $inventory->load(['branch', 'inventoryProductRecipes.productRecipe']);

        return response()->json([
            'status' => true,
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Update actual stock for a single inventory product recipe item.
     */
    public function updateItem(Request $request, InventoryProductRecipe $item): JsonResponse
    {
        $item->load('inventory');
        if (! $item->inventory || (int) $item->inventory->branch_id !== $this->getBranchId()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action: item does not belong to your branch.',
            ], 403);
        }

        $validated = $request->validate([
            'actual_stock' => 'required|numeric|min:0',
        ]);

        if ($item->inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الجرد بعد اعتماده أو رفضه',
            ], 422);
        }

        $item->update([
            'actual_stock' => $validated['actual_stock'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الرصيد الفعلي للوصفة بنجاح',
            'data' => new InventoryProductRecipeResource($item->load('productRecipe')),
        ]);
    }

    /**
     * Batch update actual stocks for multiple inventory product recipe items.
     */
    public function updateActualStocks(Request $request, Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);

        if ($inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الجرد بعد اعتماده أو رفضه',
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:inventory_product_recipes,id',
            'items.*.actual_stock' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $inventory): void {
            foreach ($validated['items'] as $itemData) {
                InventoryProductRecipe::where('id', $itemData['id'])
                    ->where('inventory_id', $inventory->id)
                    ->update([
                        'actual_stock' => $itemData['actual_stock'],
                    ]);
            }
        });

        $inventory->load(['branch', 'inventoryProductRecipes.productRecipe']);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الأرصدة الفعلية بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Change inventory status (approve or reject).
     * If approved, branch ProductRecipeStock is overwritten with actual_stock.
     */
    public function changeStatus(Request $request, Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);

        $validated = $request->validate([
            'status' => 'required|in:approve,reject',
        ]);

        if ($inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'تم اتخاذ إجراء على هذا الجرد مسبقاً ولا يمكن تغييره مجدداً',
            ], 422);
        }

        $newStatus = $validated['status'];
        $branchId = $this->getBranchId();

        DB::transaction(function () use ($inventory, $newStatus, $branchId): void {
            if ($newStatus === 'approve') {
                $inventory->load('inventoryProductRecipes');

                foreach ($inventory->inventoryProductRecipes as $item) {
                    ProductRecipeStock::updateOrCreate(
                        [
                            'branch_id' => $branchId,
                            'product_recipe_id' => $item->product_recipe_id,
                        ],
                        [
                            'stock' => $item->actual_stock,
                        ]
                    );
                }
            }

            $inventory->update([
                'status' => $newStatus,
            ]);
        });

        $inventory->load(['branch', 'inventoryProductRecipes.productRecipe']);

        $message = $newStatus === 'approve'
            ? 'تم اعتماد الجرد وتحديث أرصدة مخزون الفرع بنجاح'
            : 'تم رفض الجرد بنجاح';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => new InventoryDetailResource($inventory),
        ]);
    }
}
