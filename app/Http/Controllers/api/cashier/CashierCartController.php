<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Models\OrderAddonCart;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Services\PriceCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierCartController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator
    ) {}

    private function getLocale(Request $request): string
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();

        return str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
    }

    private function cartQuery(int $cashierId): Builder
    {
        return OrderCart::with([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])->where('cashier_id', $cashierId);
    }

    /**
     * Get all cart items for current cashier, with calculation and grand totals.
     */
    public function index(Request $request): JsonResponse
    {
        $cashierId = auth()->user()?->cashier_id;
        if (! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد جهاز الكاشير أولاً',
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

        $query = $this->cartQuery($cashierId);

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        $carts = $query->latest()->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        return response()->json([
            'status' => true,
            'data' => $calculatedItems,
            'grand_totals' => $grandTotals,
        ]);
    }

    /**
     * Add an item to cart.
     */
    public function store(Request $request): JsonResponse
    {
        $cashierId = auth()->user()?->cashier_id;
        if (! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى بدء الشيفت وتحديد جهاز الكاشير أولاً',
            ], 400);
        }

        $validated = $request->validate([
            'module' => 'required|in:takeaway,dinein,delivery',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
        ]);

        $locale = $this->getLocale($request);

        $cart = DB::transaction(function () use ($validated, $cashierId): OrderCart {
            $cart = OrderCart::create([
                'module' => $validated['module'],
                'product_id' => $validated['product_id'],
                'cashier_id' => $cashierId,
                'cashier_man_id' => auth()->id(),
                'branch_id' => auth()->user()?->branch_id,
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

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح',
            'data' => $calculated,
        ], 201);
    }

    /**
     * Show single cart item with calculations.
     */
    public function show(Request $request, OrderCart $cart): JsonResponse
    {
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
     * Update cart item (quantity, notes, module, variations, addons).
     */
    public function update(Request $request, OrderCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'module' => 'sometimes|required|in:takeaway,dinein,delivery',
            'quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
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

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث عنصر السلة بنجاح',
            'data' => $calculated,
        ]);
    }

    /**
     * Delete cart item.
     */
    public function destroy(OrderCart $cart): JsonResponse
    {
        $cart->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف العنصر من السلة بنجاح',
        ]);
    }

    /**
     * Clear all cart items for the current cashier.
     */
    public function clear(Request $request): JsonResponse
    {
        $cashierId = auth()->user()?->cashier_id;
        if (! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد جهاز الكاشير أولاً',
            ], 400);
        }

        $query = OrderCart::where('cashier_id', $cashierId);

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        $query->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
        ]);
    }
}
