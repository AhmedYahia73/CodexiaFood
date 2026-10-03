<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\OrderAddonCart;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Services\PriceCalculatorService;
use App\Services\RestaurantWorkingHoursService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserCartController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator,
        protected RestaurantWorkingHoursService $workingHoursService
    ) {}

    protected function checkRestaurantOpen(): ?JsonResponse
    {
        if (! $this->workingHoursService->isOpen()) {
            return response()->json([
                'status' => false,
                'message' => $this->workingHoursService->getClosedMessage(),
            ], 400);
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

    private function getUuId(Request $request): ?string
    {
        $uuId = $request->input('uu_id')
            ?? $request->query('uu_id')
            ?? $request->header('uu_id')
            ?? $request->header('uu-id')
            ?? $request->header('X-UU-ID');

        return is_string($uuId) && trim($uuId) !== '' ? trim($uuId) : null;
    }

    private function cartQuery(string $uuId): Builder
    {
        return OrderCart::with([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])->where('uu_id', $uuId);
    }

    /**
     * Get all cart items for a given user UUID with grand totals.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $uuId = $this->getUuId($request);
        if (! $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد معرف المستخدم (uu_id)',
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_final_price' => 0.00,
                ],
            ], 400);
        }

        $locale = $this->getLocale($request);

        $query = $this->cartQuery($uuId);

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        $carts = $query->latest('id')->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        return response()->json([
            'status' => true,
            'uu_id' => $uuId,
            'data' => $calculatedItems,
            'grand_totals' => $grandTotals,
        ]);
    }

    /**
     * Add an item to user cart.
     */
    public function store(Request $request): JsonResponse
    {
        if ($closedResponse = $this->checkRestaurantOpen()) {
            return $closedResponse;
        }

        $uuId = $this->getUuId($request);
        if (! $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد معرف المستخدم (uu_id)',
            ], 400);
        }

        $validated = $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
            'module' => 'nullable|string|in:takeaway,dinein,delivery',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $cart = DB::transaction(function () use ($validated, $uuId): OrderCart {
            $cart = OrderCart::create([
                'uu_id' => $uuId,
                'module' => $validated['module'] ?? 'delivery',
                'product_id' => $validated['product_id'],
                'cashier_id' => null,
                'cashier_man_id' => null,
                'branch_id' => null,
                'hall_table_id' => null,
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
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale);
        $calculated['uu_id'] = $uuId;

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح',
            'uu_id' => $uuId,
            'data' => $calculated,
        ], 201);
    }

    /**
     * Show single cart item with calculations.
     */
    public function show(Request $request, OrderCart $cart): JsonResponse
    {
        $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $uuId = $this->getUuId($request);
        if ($uuId && $cart->uu_id !== $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'عنصر السلة غير موجود أو لا ينتمي لهذا المستخدم',
            ], 404);
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
        $calculated['uu_id'] = $cart->uu_id;

        return response()->json([
            'status' => true,
            'data' => $calculated,
        ]);
    }

    /**
     * Update cart item (quantity, notes, module, variations, addons).
     */
    public function update(Request $request, OrderCart $cart): JsonResponse
    {
        if ($closedResponse = $this->checkRestaurantOpen()) {
            return $closedResponse;
        }

        $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $uuId = $this->getUuId($request);
        if ($uuId && $cart->uu_id !== $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'عنصر السلة غير موجود أو لا ينتمي لهذا المستخدم',
            ], 404);
        }

        $validated = $request->validate([
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
            'uu_id' => 'sometimes|nullable|string|max:255',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        DB::transaction(function () use ($validated, $cart): void {
            $updateData = [];
            if (isset($validated['module'])) {
                $updateData['module'] = $validated['module'];
            }
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
        $calculated['uu_id'] = $cart->uu_id;

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
        if ($closedResponse = $this->checkRestaurantOpen()) {
            return $closedResponse;
        }

        $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
        ]);

        $uuId = $this->getUuId($request);
        if ($uuId && $cart->uu_id !== $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'عنصر السلة غير موجود أو لا ينتمي لهذا المستخدم',
            ], 404);
        }

        $cart->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف العنصر من السلة بنجاح',
        ]);
    }

    /**
     * Clear all cart items for a given user UUID.
     */
    public function clear(Request $request): JsonResponse
    {
        if ($closedResponse = $this->checkRestaurantOpen()) {
            return $closedResponse;
        }

        $validated = $request->validate([
            'uu_id' => 'sometimes|nullable|string|max:255',
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',
        ]);

        $uuId = $this->getUuId($request);
        if (! $uuId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد معرف المستخدم (uu_id)',
            ], 400);
        }

        $query = OrderCart::where('uu_id', $uuId);

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        $query->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
        ]);
    }
}
