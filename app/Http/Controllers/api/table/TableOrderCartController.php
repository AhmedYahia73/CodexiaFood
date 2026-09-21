<?php

namespace App\Http\Controllers\api\table;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HallTable;
use App\Models\OrderAddonCart;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Services\GeofenceService;
use App\Services\PriceCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableOrderCartController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator,
        protected GeofenceService $geofenceService
    ) {}

    private function validateGeofence(Request $request, ?Branch $branch): ?JsonResponse
    {
        if ($error = $this->geofenceService->validateLocation($request, $branch)) {
            return response()->json([
                'status' => false,
                'message' => $error,
            ], 403);
        }

        return null;
    }

    private function getLocale(Request $request): string
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();

        return str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
    }

    private function resolveHallTable(array $validated): ?HallTable
    {
        if (! empty($validated['table_code'])) {
            return HallTable::with('branch')->where('code', $validated['table_code'])->first();
        }

        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;
        if ($tableId) {
            return HallTable::with('branch')->find((int) $tableId);
        }

        return null;
    }

    private function cartQuery(int $tableId): Builder
    {
        return OrderCart::with([
            'hallTable',
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])->where('hall_table_id', $tableId);
    }

    /**
     * Get all cart items for a given table, with calculation and grand totals.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_code' => 'required_without_all:table_id,hall_table_id|nullable|string|exists:hall_tables,code',
            'table_id' => 'required_without_all:table_code,hall_table_id|nullable|integer|exists:hall_tables,id',
            'hall_table_id' => 'required_without_all:table_code,table_id|nullable|integer|exists:hall_tables,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $hallTable = $this->resolveHallTable($validated);
        if (! $hallTable) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد كود الطاولة (table_code)',
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_final_price' => 0.00,
                ],
            ], 400);
        }

        if ($response = $this->validateGeofence($request, $hallTable->branch)) {
            return $response;
        }

        $locale = $this->getLocale($request);

        $carts = $this->cartQuery($hallTable->id)->latest('id')->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        return response()->json([
            'status' => true,
            'table_code' => $hallTable->code,
            'data' => $calculatedItems,
            'grand_totals' => $grandTotals,
        ]);
    }

    /**
     * Add an item to table cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_code' => 'required_without_all:table_id,hall_table_id|nullable|string|exists:hall_tables,code',
            'table_id' => 'required_without_all:table_code,hall_table_id|nullable|exists:hall_tables,id',
            'hall_table_id' => 'nullable|exists:hall_tables,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $hallTable = $this->resolveHallTable($validated);
        if (! $hallTable) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد كود الطاولة (table_code)',
            ], 400);
        }

        if ($response = $this->validateGeofence($request, $hallTable->branch)) {
            return $response;
        }

        $locale = $this->getLocale($request);

        $cart = DB::transaction(function () use ($validated, $hallTable): OrderCart {
            $cart = OrderCart::create([
                'module' => 'dinein',
                'product_id' => $validated['product_id'],
                'hall_table_id' => $hallTable->id,
                'branch_id' => $hallTable->branch_id,
                'cashier_id' => null,
                'cashier_man_id' => null,
                'quantity' => $validated['quantity'] ?? 1,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['variations'] ?? [] as $varData) {
                OrderVariationCart::create([
                    'order_cart_id' => $cart->id,
                    'variation_id' => $varData['variation_id'],
                    'option_ids' => $varData['option_ids'],
                ]);
            }

            foreach ($validated['addons'] ?? [] as $addonData) {
                OrderAddonCart::create([
                    'order_cart_id' => $cart->id,
                    'addon_id' => $addonData['addon_id'],
                ]);
            }

            return $cart;
        });

        $cart->load([
            'hallTable',
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale);
        $calculated['table_code'] = $hallTable->code;

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح',
            'table_code' => $hallTable->code,
            'data' => $calculated,
        ], 201);
    }

    /**
     * Show single cart item with calculations.
     */
    public function show(Request $request, OrderCart $cart): JsonResponse
    {
        $request->validate([
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $cart->loadMissing(['hallTable.branch', 'branch']);
        $branch = $cart->hallTable?->branch ?? $cart->branch;
        if ($response = $this->validateGeofence($request, $branch)) {
            return $response;
        }

        $locale = $this->getLocale($request);

        $cart->load([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale);

        return response()->json([
            'status' => true,
            'data' => $calculated,
        ]);
    }

    /**
     * Update cart item (quantity, notes, variations, addons).
     */
    public function update(Request $request, OrderCart $cart): JsonResponse
    {
        $cart->loadMissing(['hallTable.branch', 'branch']);
        $branch = $cart->hallTable?->branch ?? $cart->branch;
        if ($response = $this->validateGeofence($request, $branch)) {
            return $response;
        }

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        DB::transaction(function () use ($validated, $cart): void {
            $updateData = [];
            if (isset($validated['quantity'])) {
                $updateData['quantity'] = $validated['quantity'];
            }
            if (array_key_exists('notes', $validated)) {
                $updateData['notes'] = $validated['notes'];
            }

            if (! empty($updateData)) {
                $cart->update($updateData);
            }

            if (array_key_exists('variations', $validated)) {
                $cart->variationCarts()->delete();
                foreach ($validated['variations'] ?? [] as $varData) {
                    OrderVariationCart::create([
                        'order_cart_id' => $cart->id,
                        'variation_id' => $varData['variation_id'],
                        'option_ids' => $varData['option_ids'],
                    ]);
                }
            }

            if (array_key_exists('addons', $validated)) {
                $cart->addonCarts()->delete();
                foreach ($validated['addons'] ?? [] as $addonData) {
                    OrderAddonCart::create([
                        'order_cart_id' => $cart->id,
                        'addon_id' => $addonData['addon_id'],
                    ]);
                }
            }
        });

        $cart->load([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث عنصر السلة بنجاح',
            'data' => $calculated,
        ]);
    }

    /**
     * Delete cart item.
     */
    public function destroy(Request $request, OrderCart $cart): JsonResponse
    {
        $request->validate([
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
        ]);

        $cart->loadMissing(['hallTable.branch', 'branch']);
        $branch = $cart->hallTable?->branch ?? $cart->branch;
        if ($response = $this->validateGeofence($request, $branch)) {
            return $response;
        }

        $cart->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف العنصر من السلة بنجاح',
        ]);
    }

    /**
     * Clear all cart items for a given table.
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_code' => 'required_without_all:table_id,hall_table_id|nullable|string|exists:hall_tables,code',
            'table_id' => 'required_without_all:table_code,hall_table_id|nullable|integer|exists:hall_tables,id',
            'hall_table_id' => 'required_without_all:table_code,table_id|nullable|integer|exists:hall_tables,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'long' => 'nullable|numeric',
        ]);

        $hallTable = $this->resolveHallTable($validated);
        if (! $hallTable) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد كود الطاولة (table_code)',
            ], 400);
        }

        if ($response = $this->validateGeofence($request, $hallTable->branch)) {
            return $response;
        }

        OrderCart::where('hall_table_id', $hallTable->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
        ]);
    }
}
