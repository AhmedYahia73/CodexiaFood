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
     * Get select options (Materials and ProductRecipes) localized by lang key ('ar' or 'en').
     */
    public function selectOptions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptionsData($request),
        ]);
    }

    /**
     * Display a listing of purchases.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $purchases = Purchase::latest()->paginate($request->get('per_page', 15));

        return PurchaseResource::collection($purchases)->additional([
            'select_options' => $this->getSelectOptionsData($request),
        ]);
    }

    /**
     * Store newly created purchase(s) (supports single row or multi-rows).
     * Automatically increments the stock of selected Material(s) and/or ProductRecipe(s).
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Extract and validate receipt image if provided
        $request->validate([
            'receipt' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $this->upload($request, 'receipt', 'purchases');
        } elseif ($request->filled('receipt') && is_string($request->input('receipt'))) {
            $receiptPath = $request->input('receipt');
        }

        // 2. Normalize rows from request (can be single row or multi-rows in 'items' or 'purchases')
        $rows = $this->normalizeRows($request);

        if (empty($rows)) {
            return response()->json([
                'status' => false,
                'message' => 'يجب إرسال بيانات الشراء.',
            ], 422);
        }

        // 3. Validate each row
        $validatedItems = [];
        foreach ($rows as $index => $row) {
            $normalizedRow = $this->validateAndNormalizeRow($row, $index);
            if ($normalizedRow instanceof JsonResponse) {
                return $normalizedRow; // Return validation error
            }
            $validatedItems[] = $normalizedRow;
        }

        // 4. Execute creation and stock increment in a DB transaction
        $createdPurchases = DB::transaction(function () use ($validatedItems, $receiptPath) {
            $results = [];

            foreach ($validatedItems as $item) {
                $materialIds = $item['material_ids'];
                $recipeIds = $item['product_recipe_id'];
                $quantity = $item['quantity'];
                $cost = $item['cost'];
                $itemReceipt = $item['receipt'] ?? $receiptPath;

                // Increment stock for Materials
                if (! empty($materialIds)) {
                    foreach ($materialIds as $matId) {
                        $material = Material::lockForUpdate()->find($matId);
                        if ($material) {
                            $material->increment('stock', $quantity);
                        }
                    }
                }

                // Increment stock for ProductRecipes
                if (! empty($recipeIds)) {
                    foreach ($recipeIds as $recId) {
                        $recipe = ProductRecipe::lockForUpdate()->find($recId);
                        if ($recipe) {
                            $recipe->increment('stock', $quantity);
                        }
                    }
                }

                // Create Purchase record
                $purchase = Purchase::create([
                    'material_ids' => ! empty($materialIds) ? array_values(array_unique($materialIds)) : null,
                    'product_recipe_id' => ! empty($recipeIds) ? array_values(array_unique($recipeIds)) : null,
                    'quantity' => $quantity,
                    'cost' => $cost,
                    'receipt' => $itemReceipt,
                ]);

                $results[] = $purchase;
            }

            return $results;
        });

        $isMultiple = count($createdPurchases) > 1;

        return response()->json([
            'status' => true,
            'message' => $isMultiple ? 'Purchases created successfully.' : 'Purchase created successfully.',
            'data' => $isMultiple
                ? PurchaseResource::collection(collect($createdPurchases))
                : new PurchaseResource($createdPurchases[0]),
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
            'data' => new PurchaseResource($purchase),
        ]);
    }

    /**
     * Remove the specified purchase from storage.
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        if ($purchase->receipt) {
            $this->deleteImage($purchase->receipt);
        }

        $purchase->delete();

        return response()->json([
            'status' => true,
            'message' => 'Purchase deleted successfully.',
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
     * Normalize request payload into an array of purchase row items.
     */
    private function normalizeRows(Request $request): array
    {
        $inputItems = $request->input('items') ?? $request->input('purchases');

        if ($inputItems !== null) {
            if (is_string($inputItems)) {
                $decoded = json_decode($inputItems, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            } elseif (is_array($inputItems)) {
                return $inputItems;
            }
        }

        // Check if single row sent directly in request body
        if (
            $request->has('material_id') ||
            $request->has('material_ids') ||
            $request->has('product_recipe_id') ||
            $request->has('product_recipe_ids') ||
            $request->has('quantity') ||
            $request->has('cost')
        ) {
            return [$request->all()];
        }

        return [];
    }

    /**
     * Validate and normalize an individual row.
     */
    private function validateAndNormalizeRow(array $row, int $index): array|JsonResponse
    {
        // Extract Material IDs
        $materialIds = [];
        if (! empty($row['material_ids'])) {
            $mIds = $row['material_ids'];
            if (is_string($mIds)) {
                $decoded = json_decode($mIds, true);
                $mIds = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : explode(',', $mIds);
            }
            $materialIds = is_array($mIds) ? array_map('intval', $mIds) : [(int) $mIds];
        } elseif (! empty($row['material_id'])) {
            $mId = $row['material_id'];
            if (is_string($mId) && str_starts_with($mId, '[')) {
                $decoded = json_decode($mId, true);
                $materialIds = is_array($decoded) ? array_map('intval', $decoded) : [(int) $mId];
            } else {
                $materialIds = [(int) $mId];
            }
        }

        // Extract ProductRecipe IDs
        $recipeIds = [];
        $rInput = $row['product_recipe_id'] ?? $row['product_recipe_ids'] ?? null;
        if (! empty($rInput)) {
            if (is_string($rInput)) {
                $decoded = json_decode($rInput, true);
                $rInput = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : explode(',', $rInput);
            }
            $recipeIds = is_array($rInput) ? array_map('intval', $rInput) : [(int) $rInput];
        }

        // Validate that at least one material or product recipe is selected (or twice/both)
        if (empty($materialIds) && empty($recipeIds)) {
            return response()->json([
                'status' => false,
                'message' => "يجب تحديد مادة خام (material_id) أو وصفة منتج (product_recipe_id) أو كليهما في العنصر رقم {$index}.",
            ], 422);
        }

        // Verify existence of Material IDs
        if (! empty($materialIds)) {
            $existingCount = Material::whereIn('id', $materialIds)->count();
            if ($existingCount !== count(array_unique($materialIds))) {
                return response()->json([
                    'status' => false,
                    'message' => "إحدى المواد الخام المحددة في العنصر رقم {$index} غير موجودة.",
                ], 422);
            }
        }

        // Verify existence of ProductRecipe IDs
        if (! empty($recipeIds)) {
            $existingCount = ProductRecipe::whereIn('id', $recipeIds)->count();
            if ($existingCount !== count(array_unique($recipeIds))) {
                return response()->json([
                    'status' => false,
                    'message' => "إحدى وصفات المنتجات المحددة في العنصر رقم {$index} غير موجودة.",
                ], 422);
            }
        }

        // Validate Quantity
        if (! isset($row['quantity']) || ! is_numeric($row['quantity']) || (float) $row['quantity'] <= 0) {
            return response()->json([
                'status' => false,
                'message' => "يجب أن تكون الكمية (quantity) رقماً أكبر من الصفر في العنصر رقم {$index}.",
            ], 422);
        }

        // Validate Cost
        if (! isset($row['cost']) || ! is_numeric($row['cost']) || (float) $row['cost'] < 0) {
            return response()->json([
                'status' => false,
                'message' => "يجب أن تكون التكلفة (cost) رقماً غير سالب في العنصر رقم {$index}.",
            ], 422);
        }

        return [
            'material_ids' => $materialIds,
            'product_recipe_id' => $recipeIds,
            'quantity' => (float) $row['quantity'],
            'cost' => (float) $row['cost'],
            'receipt' => $row['receipt'] ?? null,
        ];
    }
}
