<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Sale;
use App\Models\Saler;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    use image;

    /**
     * Get select options for sales (Branches, Salers, Products, Materials, ProductRecipes).
     */
    public function selectOptions(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'sometimes|nullable|string|in:ar,en',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptionsData($request),
        ]);
    }

    /**
     * Display a listing of sales with pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:-1',
            'lang' => 'sometimes|nullable|string|in:ar,en',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'saler_id' => 'sometimes|nullable|integer|exists:salers,id',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $query = Sale::with([
            'branch',
            'saler',
            'items.product',
            'items.material',
            'items.productRecipe',
            'dues',
        ])->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('saler_id')) {
            $query->where('saler_id', $request->saler_id);
        }

        $sales = $perPage === -1
            ? $query->paginate(perPage: 1000, page: 1)
            : $query->paginate(perPage: $perPage, page: $page);

        return SaleResource::collection($sales);
    }

    /**
     * Store newly created sale with stock deduction and dues tracking.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeItemsInput($request);

        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'saler_id' => 'nullable|integer|exists:salers,id',
            'paid_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'date' => 'nullable|date',
            'receipt' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'notes' => 'nullable|string',
            'without_recipe' => 'sometimes|nullable|boolean',
            'dues' => 'nullable|array',
            'dues.*.amount' => 'required_with:dues|numeric|min:0.01',
            'dues.*.date' => 'required_with:dues|date',
            'dues.*.notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.material_id' => 'nullable|integer|exists:materials,id',
            'items.*.product_recipe_id' => 'nullable|integer|exists:product_recipes,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost' => 'required|numeric|min:0',
        ]);

        // 1. Ensure each item specifies at least one product, material, or recipe
        foreach ($validated['items'] as $index => $item) {
            if (empty($item['product_id']) && empty($item['material_id']) && empty($item['product_recipe_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => "يجب تحديد منتج أو مادة خام أو وصفة في البند رقم {$index}.",
                ], 422);
            }
        }

        $branchId = (int) $validated['branch_id'];
        $withoutRecipe = $request->boolean('without_recipe');

        // 2. Pre-validate stock availability unless without_recipe is true
        if (! $withoutRecipe) {
            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];

                // Case A: Selling Finished Product (checks BOM recipe components)
                if (! empty($item['product_id'])) {
                    $spec = ProductManufacturing::with([
                        'productRecipeManufacturings.material',
                        'productRecipeManufacturings.productRecipe',
                    ])
                        ->where('product_id', $item['product_id'])
                        ->latest('id')
                        ->first();

                    if ($spec && $spec->productRecipeManufacturings->isNotEmpty()) {
                        foreach ($spec->productRecipeManufacturings as $recipeItem) {
                            $requiredQty = (float) $recipeItem->count * $qty;
                            if ($requiredQty <= 0) {
                                continue;
                            }

                            if (! empty($recipeItem->material_id)) {
                                $currentStock = (float) (MaterialStock::where('material_id', $recipeItem->material_id)
                                    ->where('branch_id', $branchId)
                                    ->value('stock') ?? 0);

                                if ($currentStock < $requiredQty) {
                                    $name = $recipeItem->material?->name;
                                    $label = is_array($name) ? ($name['ar'] ?? $name['en'] ?? "مادة خام #{$recipeItem->material_id}") : ($name ?? "مادة خام #{$recipeItem->material_id}");

                                    return response()->json([
                                        'status' => false,
                                        'message' => "المخزون المتوفر للمادة الخام ({$label}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                                        'insufficient_ingredient' => [
                                            'type' => 'material',
                                            'id' => $recipeItem->material_id,
                                            'name' => $label,
                                            'available_stock' => $currentStock,
                                            'required_quantity' => $requiredQty,
                                            'can_bypass' => true,
                                        ],
                                    ], 422);
                                }
                            } elseif (! empty($recipeItem->product_recipe_id)) {
                                $currentStock = (float) (ProductRecipeStock::where('product_recipe_id', $recipeItem->product_recipe_id)
                                    ->where('branch_id', $branchId)
                                    ->value('stock') ?? 0);

                                if ($currentStock < $requiredQty) {
                                    $name = $recipeItem->productRecipe?->name;
                                    $label = is_array($name) ? ($name['ar'] ?? $name['en'] ?? "وصفة #{$recipeItem->product_recipe_id}") : ($name ?? "وصفة #{$recipeItem->product_recipe_id}");

                                    return response()->json([
                                        'status' => false,
                                        'message' => "المخزون المتوفر للوصفة ({$label}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                                        'insufficient_ingredient' => [
                                            'type' => 'recipe',
                                            'id' => $recipeItem->product_recipe_id,
                                            'name' => $label,
                                            'available_stock' => $currentStock,
                                            'required_quantity' => $requiredQty,
                                            'can_bypass' => true,
                                        ],
                                    ], 422);
                                }
                            }
                        }
                    }
                }

                // Case B: Selling Material directly
                if (! empty($item['material_id'])) {
                    $currentStock = (float) (MaterialStock::where('material_id', $item['material_id'])
                        ->where('branch_id', $branchId)
                        ->value('stock') ?? 0);

                    if ($currentStock < $qty) {
                        $material = Material::find($item['material_id']);
                        $name = $material?->name;
                        $label = is_array($name) ? ($name['ar'] ?? $name['en'] ?? "مادة خام #{$item['material_id']}") : ($name ?? "مادة خام #{$item['material_id']}");

                        return response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر للمادة الخام ({$label}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$qty}.",
                            'insufficient_ingredient' => [
                                'type' => 'material',
                                'id' => $item['material_id'],
                                'name' => $label,
                                'available_stock' => $currentStock,
                                'required_quantity' => $qty,
                                'can_bypass' => true,
                            ],
                        ], 422);
                    }
                }

                // Case C: Selling ProductRecipe directly
                if (! empty($item['product_recipe_id'])) {
                    $currentStock = (float) (ProductRecipeStock::where('product_recipe_id', $item['product_recipe_id'])
                        ->where('branch_id', $branchId)
                        ->value('stock') ?? 0);

                    if ($currentStock < $qty) {
                        $recipe = ProductRecipe::find($item['product_recipe_id']);
                        $name = $recipe?->name;
                        $label = is_array($name) ? ($name['ar'] ?? $name['en'] ?? "وصفة #{$item['product_recipe_id']}") : ($name ?? "وصفة #{$item['product_recipe_id']}");

                        return response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر للوصفة ({$label}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$qty}.",
                            'insufficient_ingredient' => [
                                'type' => 'recipe',
                                'id' => $item['product_recipe_id'],
                                'name' => $label,
                                'available_stock' => $currentStock,
                                'required_quantity' => $qty,
                                'can_bypass' => true,
                            ],
                        ], 422);
                    }
                }
            }
        }

        // 3. Receipt Image
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $this->upload($request, 'receipt', 'sales');
        } elseif ($request->filled('receipt') && is_string($request->input('receipt'))) {
            $receiptPath = $request->input('receipt');
        }

        // 4. Create Sale and deduct stocks inside DB Transaction
        $sale = DB::transaction(function () use ($validated, $branchId, $withoutRecipe, $receiptPath) {
            $totalCost = 0;
            $totalQuantity = 0;

            foreach ($validated['items'] as $itemData) {
                $totalCost += (float) $itemData['cost'];
                $totalQuantity += (float) $itemData['quantity'];
            }

            $paidAmount = isset($validated['paid_amount'])
                ? min($totalCost, max(0.0, (float) $validated['paid_amount']))
                : $totalCost;
            $remainingAmount = max(0.0, $totalCost - $paidAmount);

            $sale = Sale::create([
                'branch_id' => $branchId,
                'saler_id' => ! empty($validated['saler_id']) ? (int) $validated['saler_id'] : null,
                'receipt' => $receiptPath,
                'notes' => $validated['notes'] ?? null,
                'total_cost' => $totalCost,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'total_quantity' => $totalQuantity,
            ]);

            // Deduct stocks for items
            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $productId = ! empty($itemData['product_id']) ? (int) $itemData['product_id'] : null;
                $materialId = ! empty($itemData['material_id']) ? (int) $itemData['material_id'] : null;
                $recipeId = ! empty($itemData['product_recipe_id']) ? (int) $itemData['product_recipe_id'] : null;

                // Create SaleItem
                $sale->items()->create([
                    'product_id' => $productId,
                    'material_id' => $materialId,
                    'product_recipe_id' => $recipeId,
                    'quantity' => $qty,
                    'cost' => (float) $itemData['cost'],
                ]);

                // Deduct stock for product's BOM recipe components
                if ($productId) {
                    $spec = ProductManufacturing::with([
                        'productRecipeManufacturings.material',
                        'productRecipeManufacturings.productRecipe',
                    ])
                        ->where('product_id', $productId)
                        ->latest('id')
                        ->first();

                    if ($spec && $spec->productRecipeManufacturings->isNotEmpty()) {
                        foreach ($spec->productRecipeManufacturings as $recipeItem) {
                            $requiredQty = (float) $recipeItem->count * $qty;
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
                                    $deduct = $withoutRecipe
                                        ? min(max(0.0, $current), $requiredQty)
                                        : $requiredQty;

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
                                    $deduct = $withoutRecipe
                                        ? min(max(0.0, $current), $requiredQty)
                                        : $requiredQty;

                                    if ($deduct > 0) {
                                        $recStock->decrement('stock', $deduct);
                                    }
                                }
                            }
                        }
                    }
                }

                // Deduct stock for direct material sale
                if ($materialId) {
                    $matStock = MaterialStock::where('material_id', $materialId)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();

                    if ($matStock) {
                        $current = (float) $matStock->stock;
                        $deduct = $withoutRecipe
                            ? min(max(0.0, $current), $qty)
                            : $qty;

                        if ($deduct > 0) {
                            $matStock->decrement('stock', $deduct);
                        }
                    }
                }

                // Deduct stock for direct recipe sale
                if ($recipeId) {
                    $recStock = ProductRecipeStock::where('product_recipe_id', $recipeId)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();

                    if ($recStock) {
                        $current = (float) $recStock->stock;
                        $deduct = $withoutRecipe
                            ? min(max(0.0, $current), $qty)
                            : $qty;

                        if ($deduct > 0) {
                            $recStock->decrement('stock', $deduct);
                        }
                    }
                }
            }

            // Record dues and update Saler balance if remaining amount > 0
            if ($remainingAmount > 0) {
                if (! empty($validated['dues']) && is_array($validated['dues'])) {
                    foreach ($validated['dues'] as $dueItem) {
                        $dueAmount = (float) ($dueItem['amount'] ?? 0);
                        if ($dueAmount <= 0) {
                            continue;
                        }
                        $sale->dues()->create([
                            'amount' => $dueAmount,
                            'date' => ! empty($dueItem['date']) ? $dueItem['date'] : now()->addMonth()->format('Y-m-d'),
                            'notes' => $dueItem['notes'] ?? ($validated['notes'] ?? null),
                        ]);
                    }
                } else {
                    $dueDate = ! empty($validated['due_date'])
                        ? $validated['due_date']
                        : (! empty($validated['date']) ? $validated['date'] : now()->addMonth()->format('Y-m-d'));

                    $sale->dues()->create([
                        'amount' => $remainingAmount,
                        'date' => $dueDate,
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                if ($sale->saler_id) {
                    Saler::where('id', $sale->saler_id)->increment('balance', $remainingAmount);
                }
            }

            return $sale;
        });

        return response()->json([
            'status' => true,
            'message' => 'Sale invoice created successfully.',
            'data' => new SaleResource($sale->load([
                'branch',
                'saler',
                'items.product',
                'items.material',
                'items.productRecipe',
                'dues',
            ])),
        ], 201);
    }

    /**
     * Display the specified sale invoice.
     */
    public function show(Sale $sale): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new SaleResource($sale->load([
                'branch',
                'saler',
                'items.product',
                'items.material',
                'items.productRecipe',
                'dues',
            ])),
        ]);
    }

    /**
     * Remove the specified sale invoice, restore stock, and reverse saler balance.
     */
    public function destroy(Sale $sale): JsonResponse
    {
        DB::transaction(function () use ($sale) {
            $branchId = $sale->branch_id;
            $items = $sale->items()->get();

            // 1. Restore stock for each item sold
            foreach ($items as $item) {
                $qty = (float) $item->quantity;

                // Restore product BOM components
                if ($item->product_id && $branchId) {
                    $spec = ProductManufacturing::with([
                        'productRecipeManufacturings.material',
                        'productRecipeManufacturings.productRecipe',
                    ])
                        ->where('product_id', $item->product_id)
                        ->latest('id')
                        ->first();

                    if ($spec && $spec->productRecipeManufacturings->isNotEmpty()) {
                        foreach ($spec->productRecipeManufacturings as $recipeItem) {
                            $requiredQty = (float) $recipeItem->count * $qty;
                            if ($requiredQty <= 0) {
                                continue;
                            }

                            if (! empty($recipeItem->material_id)) {
                                $matStock = MaterialStock::where('material_id', $recipeItem->material_id)
                                    ->where('branch_id', $branchId)
                                    ->lockForUpdate()
                                    ->first();
                                $matStock?->increment('stock', $requiredQty);
                            } elseif (! empty($recipeItem->product_recipe_id)) {
                                $recStock = ProductRecipeStock::where('product_recipe_id', $recipeItem->product_recipe_id)
                                    ->where('branch_id', $branchId)
                                    ->lockForUpdate()
                                    ->first();
                                $recStock?->increment('stock', $requiredQty);
                            }
                        }
                    }
                }

                // Restore material
                if ($item->material_id && $branchId) {
                    $matStock = MaterialStock::where('material_id', $item->material_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    $matStock?->increment('stock', $qty);
                }

                // Restore recipe
                if ($item->product_recipe_id && $branchId) {
                    $recStock = ProductRecipeStock::where('product_recipe_id', $item->product_recipe_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    $recStock?->increment('stock', $qty);
                }
            }

            // 2. Reverse Saler balance if there was a deferred remaining amount
            if ($sale->saler_id && (float) $sale->remaining_amount > 0) {
                Saler::where('id', $sale->saler_id)->decrement('balance', (float) $sale->remaining_amount);
            }

            // 3. Delete receipt file
            if ($sale->receipt) {
                $this->deleteImage($sale->receipt);
            }

            // 4. Delete sale (cascades items and dues)
            $sale->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Sale deleted successfully, stock restored, and balance updated.',
        ]);
    }

    /**
     * Get select options data.
     */
    private function getSelectOptionsData(?Request $request = null): array
    {
        $lang = $request?->query('lang')
            ?? $request?->header('Accept-Language')
            ?? $request?->header('lang')
            ?? app()->getLocale();

        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
        $branchId = $request?->query('branch_id');

        $branches = Branch::select('id', 'name')->get()->map(function ($branch) use ($locale) {
            return [
                'id' => $branch->id,
                'name' => is_array($branch->name)
                    ? ($branch->name[$locale] ?? $branch->name['ar'] ?? $branch->name['en'] ?? '')
                    : $branch->name,
            ];
        });

        $salers = Saler::select('id', 'name', 'phone', 'balance')->get()->map(function ($saler) use ($locale) {
            return [
                'id' => $saler->id,
                'name' => is_array($saler->name)
                    ? ($saler->name[$locale] ?? $saler->name['ar'] ?? $saler->name['en'] ?? '')
                    : $saler->name,
                'phone' => $saler->phone,
                'balance' => (float) $saler->balance,
            ];
        });

        $products = Product::select('id', 'name', 'price')->get()->map(function ($product) use ($locale) {
            return [
                'id' => $product->id,
                'name' => is_array($product->name)
                    ? ($product->name[$locale] ?? $product->name['ar'] ?? $product->name['en'] ?? '')
                    : $product->name,
                'price' => (float) $product->price,
            ];
        });

        $materials = Material::select('id', 'name')->get()->map(function ($item) use ($locale, $branchId) {
            return [
                'id' => $item->id,
                'name' => is_array($item->name)
                    ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                    : $item->name,
                'stock' => $branchId ? (float) $item->stockForBranch((int) $branchId) : (float) $item->totalStock(),
            ];
        });

        $productRecipes = ProductRecipe::select('id', 'name')->get()->map(function ($item) use ($locale, $branchId) {
            return [
                'id' => $item->id,
                'name' => is_array($item->name)
                    ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                    : $item->name,
                'stock' => $branchId ? (float) $item->stockForBranch((int) $branchId) : (float) $item->totalStock(),
            ];
        });

        return [
            'branches' => $branches,
            'salers' => $salers,
            'products' => $products,
            'materials' => $materials,
            'product_recipes' => $productRecipes,
        ];
    }

    /**
     * Normalize items input from string or root parameters.
     */
    private function normalizeItemsInput(Request $request): void
    {
        $inputItems = $request->input('items') ?? $request->input('sales');

        if ($inputItems !== null) {
            if (is_string($inputItems)) {
                $decoded = json_decode($inputItems, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['items' => $decoded]);
                }
            }
        }

        if ($request->has('dues') && is_string($request->input('dues'))) {
            $decodedDues = json_decode($request->input('dues'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDues)) {
                $request->merge(['dues' => $decodedDues]);
            }
        }
    }
}
