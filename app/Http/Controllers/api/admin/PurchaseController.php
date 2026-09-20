<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Material;
use App\Models\ProductRecipe;
use App\Models\Purchase;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    use image;

    /**
     * Get select options (Materials and ProductRecipes) with id and name localized by lang.
     */
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

    /**
     * Display a listing of purchases with pagination and loaded items.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:-1',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $query = Purchase::with(['items.material', 'items.productRecipe'])->latest();

        $purchases = $perPage === -1
            ? $query->paginate(perPage: 1000, page: 1)
            : $query->paginate(perPage: $perPage, page: $page);

        return PurchaseResource::collection($purchases)->additional([
            'status' => true,
            'select_options' => $this->getSelectOptionsData($request),
        ]);
    }

    /**
     * Store newly created purchase with individual items.
     * Each item has its own quantity and cost, and increments the stock of its selected Material/ProductRecipe.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Normalize items if passed as JSON string (common in multipart/form-data with receipt image)
        $this->normalizeItemsInput($request);

        // 2. Validate request parameters (explicit keys for Scramble)
        $validated = $request->validate([
            'receipt' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'nullable|integer|exists:materials,id',
            'items.*.product_recipe_id' => 'nullable|integer|exists:product_recipes,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost' => 'required|numeric|min:0',
        ]);

        // 3. Ensure each item specifies at least one material_id or product_recipe_id
        foreach ($validated['items'] as $index => $item) {
            if (empty($item['material_id']) && empty($item['product_recipe_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => "يجب تحديد مادة خام (material_id) أو وصفة منتج (product_recipe_id) أو كليهما في العنصر رقم {$index}.",
                ], 422);
            }
        }

        // 4. Handle receipt image upload
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $this->upload($request, 'receipt', 'purchases');
        } elseif ($request->filled('receipt') && is_string($request->input('receipt'))) {
            $receiptPath = $request->input('receipt');
        }

        // 5. Create Purchase, PurchaseItems, and increment stocks inside a DB transaction
        $purchase = DB::transaction(function () use ($validated, $receiptPath) {
            $totalCost = 0;
            $totalQuantity = 0;

            $purchase = Purchase::create([
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

                // Increment Material stock by this item's quantity
                if ($materialId) {
                    $material = Material::lockForUpdate()->find($materialId);
                    $material?->increment('stock', $qty);
                }

                // Increment ProductRecipe stock by this item's quantity
                if ($recipeId) {
                    $recipe = ProductRecipe::lockForUpdate()->find($recipeId);
                    $recipe?->increment('stock', $qty);
                }

                // Create PurchaseItem
                $purchase->items()->create([
                    'material_id' => $materialId,
                    'product_recipe_id' => $recipeId,
                    'quantity' => $qty,
                    'cost' => $itemCost,
                ]);

                $totalCost += $itemCost;
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
            'data' => new PurchaseResource($purchase->load(['items.material', 'items.productRecipe'])),
            'select_options' => $this->getSelectOptionsData($request),
        ], 201);
    }

    /**
     * Display the specified purchase.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new PurchaseResource($purchase->load(['items.material', 'items.productRecipe'])),
        ]);
    }

    /**
     * Remove the specified purchase from storage and restore stock.
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        DB::transaction(function () use ($purchase) {
            $items = $purchase->items()->get();

            // Restore/decrement stock by the purchased quantity
            foreach ($items as $item) {
                if ($item->material_id) {
                    $material = Material::lockForUpdate()->find($item->material_id);
                    $material?->decrement('stock', $item->quantity);
                }

                if ($item->product_recipe_id) {
                    $recipe = ProductRecipe::lockForUpdate()->find($item->product_recipe_id);
                    $recipe?->decrement('stock', $item->quantity);
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

    /**
     * Extract select options data localized by language key.
     */
    private function getSelectOptionsData(?Request $request = null): array
    {
        $lang = $request?->query('lang')
            ?? $request?->header('Accept-Language')
            ?? $request?->header('lang')
            ?? app()->getLocale();

        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $materials = Material::select('id', 'name', 'stock')
            ->get()
            ->map(function ($item) use ($locale) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name)
                        ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                        : $item->name,
                    'stock' => (int) $item->stock,
                ];
            });

        $productRecipes = ProductRecipe::select('id', 'name', 'stock')
            ->get()
            ->map(function ($item) use ($locale) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name)
                        ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '')
                        : $item->name,
                    'stock' => (int) $item->stock,
                ];
            });

        return [
            'materials' => $materials,
            'product_recipes' => $productRecipes,
        ];
    }

    /**
     * Normalize items input from string or root parameters.
     */
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
        } elseif ($request->has('quantity') && ($request->has('material_id') || $request->has('product_recipe_id'))) {
            $request->merge([
                'items' => [
                    [
                        'material_id' => $request->input('material_id'),
                        'product_recipe_id' => $request->input('product_recipe_id'),
                        'quantity' => $request->input('quantity'),
                        'cost' => $request->input('cost', 0),
                    ],
                ],
            ]);
        }
    }
}
