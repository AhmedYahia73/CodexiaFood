<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\PurchaseResource;
use App\Models\Addon;
use App\Models\AddonStock;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\UnitConversionService;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseController extends BaseBranchController
{
    use image;

    public function selectOptions(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptionsData($request),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:-1',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $query = Purchase::with(['branch', 'supplier', 'items.material', 'items.productRecipe', 'items.addon'])
            ->where('branch_id', $branchId)
            ->latest();

        $purchases = $perPage === -1
            ? $query->paginate(perPage: 1000, page: 1)
            : $query->paginate(perPage: $perPage, page: $page);

        return PurchaseResource::collection($purchases)->additional([
            'status' => true,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $this->normalizeItemsInput($request);

        $validated = $request->validate([
            'branch_id' => 'sometimes|nullable|integer',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'receipt' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'nullable|integer|exists:materials,id',
            'items.*.product_recipe_id' => 'nullable|integer|exists:product_recipes,id',
            'items.*.addon_id' => 'nullable|integer|exists:addons,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost' => 'required|numeric|min:0',
            'items.*.unit_id' => 'sometimes|nullable|integer|exists:units,id',
        ]);

        foreach ($validated['items'] as $index => $item) {
            if (empty($item['material_id']) && empty($item['product_recipe_id']) && empty($item['addon_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => "يجب تحديد مادة خام (material_id) أو وصفة منتج (product_recipe_id) أو إضافة (addon_id) في العنصر رقم {$index}.",
                ], 422);
            }
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $this->upload($request, 'receipt', 'purchases');
        } elseif ($request->filled('receipt') && is_string($request->input('receipt'))) {
            $receiptPath = $request->input('receipt');
        }

        $purchase = DB::transaction(function () use ($validated, $receiptPath, $branchId) {
            $totalCost = 0;
            $totalQuantity = 0;

            $purchase = Purchase::create([
                'branch_id' => $branchId,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'receipt' => $receiptPath,
                'notes' => $validated['notes'] ?? null,
                'total_cost' => 0,
                'total_quantity' => 0,
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $itemCost = (float) $itemData['cost'];
                $materialId = ! empty($itemData['material_id']) ? (int) $itemData['material_id'] : null;
                $recipeId = ! empty($itemData['product_recipe_id']) ? (int) $itemData['product_recipe_id'] : null;
                $addonId = ! empty($itemData['addon_id']) ? (int) $itemData['addon_id'] : null;

                if ($materialId) {
                    $matStock = MaterialStock::firstOrCreate(
                        ['material_id' => $materialId, 'branch_id' => $branchId],
                        ['stock' => 0, 'unit_id' => $itemData['unit_id'] ?? null]
                    );

                    if (! $matStock->unit_id && ! empty($itemData['unit_id'])) {
                        $matStock->update(['unit_id' => $itemData['unit_id']]);
                    }

                    $incrementQty = UnitConversionService::convert($qty, $itemData['unit_id'] ?? null, $matStock->unit_id);
                    $matStock->increment('stock', $incrementQty);

                    if (Schema::hasColumn('materials', 'stock')) {
                        Material::where('id', $materialId)->increment('stock', $incrementQty);
                    }
                }

                if ($recipeId) {
                    $recStock = ProductRecipeStock::firstOrCreate(
                        ['product_recipe_id' => $recipeId, 'branch_id' => $branchId],
                        ['stock' => 0, 'unit_id' => $itemData['unit_id'] ?? null]
                    );

                    if (! $recStock->unit_id && ! empty($itemData['unit_id'])) {
                        $recStock->update(['unit_id' => $itemData['unit_id']]);
                    }

                    $incrementQty = UnitConversionService::convert($qty, $itemData['unit_id'] ?? null, $recStock->unit_id);
                    $recStock->increment('stock', $incrementQty);

                    if (Schema::hasColumn('product_recipes', 'stock')) {
                        ProductRecipe::where('id', $recipeId)->increment('stock', $incrementQty);
                    }
                }

                if ($addonId) {
                    $addonStock = AddonStock::firstOrCreate(
                        ['addon_id' => $addonId, 'branch_id' => $branchId],
                        ['stock' => 0, 'unit_id' => $itemData['unit_id'] ?? null]
                    );

                    if (! $addonStock->unit_id && ! empty($itemData['unit_id'])) {
                        $addonStock->update(['unit_id' => $itemData['unit_id']]);
                    }

                    $incrementQty = UnitConversionService::convert($qty, $itemData['unit_id'] ?? null, $addonStock->unit_id);
                    $addonStock->increment('stock', $incrementQty);
                }

                $purchase->items()->create([
                    'material_id' => $materialId,
                    'product_recipe_id' => $recipeId,
                    'addon_id' => $addonId,
                    'quantity' => $qty,
                    'cost' => $itemCost,
                ]);

                $totalCost += ($qty * $itemCost);
                $totalQuantity += $qty;
            }

            $purchase->update([
                'total_cost' => $totalCost,
                'total_quantity' => $totalQuantity,
                'cost' => $totalCost,
                'quantity' => $totalQuantity,
            ]);

            return $purchase;
        });

        return response()->json([
            'status' => true,
            'message' => 'Purchase created successfully.',
            'data' => new PurchaseResource($purchase->load(['branch', 'supplier', 'items.material', 'items.productRecipe', 'items.addon'])),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $this->ensureBelongsToBranch($purchase);

        return response()->json([
            'status' => true,
            'data' => new PurchaseResource($purchase->load(['branch', 'supplier', 'items.material', 'items.productRecipe', 'items.addon'])),
        ]);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $this->ensureBelongsToBranch($purchase);
        $branchId = $this->getBranchId();

        DB::transaction(function () use ($purchase, $branchId) {
            $items = $purchase->items()->get();

            foreach ($items as $item) {
                if ($item->material_id) {
                    $matStock = MaterialStock::where('material_id', $item->material_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    $matStock?->decrement('stock', $item->quantity);

                    if (Schema::hasColumn('materials', 'stock')) {
                        Material::where('id', $item->material_id)->decrement('stock', $item->quantity);
                    }
                }

                if ($item->product_recipe_id) {
                    $recStock = ProductRecipeStock::where('product_recipe_id', $item->product_recipe_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    $recStock?->decrement('stock', $item->quantity);

                    if (Schema::hasColumn('product_recipes', 'stock')) {
                        ProductRecipe::where('id', $item->product_recipe_id)->decrement('stock', $item->quantity);
                    }
                }

                if ($item->addon_id) {
                    $addonStock = AddonStock::where('addon_id', $item->addon_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    $addonStock?->decrement('stock', $item->quantity);
                }
            }

            if ($purchase->receipt) {
                $this->deleteImage($purchase->receipt);
            }

            $purchase->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Purchase deleted successfully and stock restored.',
        ]);
    }

    private function getSelectOptionsData(?Request $request = null): array
    {
        $branchId = $this->getBranchId();

        $lang = $request?->query('lang')
            ?? $request?->header('Accept-Language')
            ?? $request?->header('lang')
            ?? app()->getLocale();

        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $materials = Material::select('id', 'name')
            ->get()
            ->map(function ($item) use ($locale, $branchId) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name)
                        ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                        : $item->name,
                    'stock' => (float) $item->stockForBranch($branchId),
                ];
            });

        $productRecipes = ProductRecipe::select('id', 'name')
            ->get()
            ->map(function ($item) use ($locale, $branchId) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name)
                        ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                        : $item->name,
                    'stock' => (float) $item->stockForBranch($branchId),
                ];
            });

        $addons = Addon::select('id', 'name', 'price')
            ->get()
            ->map(function ($item) use ($locale, $branchId) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name)
                        ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                        : $item->name,
                    'price' => (float) $item->price,
                    'stock' => (float) $item->stockForBranch($branchId),
                ];
            });

        $suppliers = Supplier::select('id', 'name')->get()->map(function ($supplier) use ($locale) {
            return [
                'id' => $supplier->id,
                'name' => is_array($supplier->name)
                    ? ($supplier->name[$locale] ?? $supplier->name['ar'] ?? $supplier->name['en'] ?? '')
                    : $supplier->name,
            ];
        });

        $branches = Branch::where('id', $branchId)->select('id', 'name')->get()->map(function ($branch) use ($locale) {
            return [
                'id' => $branch->id,
                'name' => is_array($branch->name)
                    ? ($branch->name[$locale] ?? $branch->name['ar'] ?? $branch->name['en'] ?? '')
                    : $branch->name,
            ];
        });

        return [
            'branches' => $branches,
            'suppliers' => $suppliers,
            'materials' => $materials,
            'product_recipes' => $productRecipes,
            'addons' => $addons,
            'units' => Unit::select('id', 'name', 'value')->get(),
        ];
    }

    private function normalizeItemsInput(Request $request): void
    {
        $inputItems = $request->input('items') ?? $request->input('purchases');

        if ($inputItems !== null) {
            if (is_string($inputItems)) {
                $decoded = json_decode($inputItems, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['items' => $decoded]);
                }
            }
        } elseif ($request->has('quantity') && ($request->has('material_id') || $request->has('product_recipe_id') || $request->has('addon_id'))) {
            $request->merge([
                'items' => [
                    [
                        'material_id' => $request->input('material_id'),
                        'product_recipe_id' => $request->input('product_recipe_id'),
                        'addon_id' => $request->input('addon_id'),
                        'quantity' => $request->input('quantity'),
                        'cost' => $request->input('cost', 0),
                    ],
                ],
            ]);
        }
    }
}
