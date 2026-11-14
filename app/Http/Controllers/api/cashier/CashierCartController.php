<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetup;
use App\Models\Cashier;
use App\Models\Discount;
use App\Models\OrderAddonCart;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Services\KitchenDispatchService;
use App\Services\PriceCalculatorService;
use App\Services\ProductManufacturingService;
use App\trait\CashierCartScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CashierCartController extends Controller
{
    use CashierCartScope;

    public function __construct(
        protected PriceCalculatorService $priceCalculator
    ) {}

    /**
     * Get all cart items for current cashier, with calculation and grand totals.
     */
    public function index(Request $request): JsonResponse
    {
        $tableId = $request->input('table_id') ?? $request->input('hall_table_id');
        $cashierId = auth()->user()?->cashier_id;

        if (! $tableId && ! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد جهاز الكاشير أولاً',
                'without_tax' => false,
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_total_dinein_tax' => 0.00,
                    'grand_final_price' => 0.00,
                    'without_tax' => false,
                ],
            ], 400);
        }

        $validated = $request->validate([
            /**
             * Order module enum.
             *
             * @var string
             *
             * @example takeaway
             */
            'module' => 'required|in:takeaway,dinein,delivery',

            /**
             * Hall Table ID for dinein cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Hall Table ID alias for dinein cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Response language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',

            /**
             * Switch to cancel / bypass all taxes (tax_id & business_setup.dinein_tax). Default is false.
             *
             * @var bool|null
             *
             * @example false
             */
            'without_tax' => 'sometimes|nullable',
            'no_tax' => 'sometimes|nullable',
            'cancel_tax' => 'sometimes|nullable',
            'exclude_tax' => 'sometimes|nullable',
        ]);

        $locale = $this->getLocale($request);
        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;

        if ($validated['module'] === 'dinein' && ! $tableId) {
            return response()->json([
                'status' => true,
                'without_tax' => false,
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_total_dinein_tax' => 0.00,
                    'grand_final_price' => 0.00,
                    'without_tax' => false,
                ],
            ]);
        }

        $withoutTax = $this->resolveWithoutTax($request, $validated['module'], $tableId, $cashierId);

        $query = $this->cartQuery($cashierId, $validated['module'] === 'dinein' ? $tableId : null)
            ->where('module', $validated['module']);

        $carts = $query->orderByRaw('COALESCE(parent_id, id) ASC, id ASC')->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale, null, $withoutTax)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems, $withoutTax);

        return response()->json([
            'status' => true,
            'without_tax' => $withoutTax,
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
            /**
             * Order module enum.
             *
             * @var string
             *
             * @example takeaway
             */
            'module' => 'required|in:takeaway,dinein,delivery',

            /**
             * Hall Table ID for dinein.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Hall Table ID alias for dinein.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Product ID.
             *
             * @var int
             *
             * @example 1
             */
            'product_id' => 'required|exists:products,id',

            /**
             * Quantity.
             *
             * @var int|null
             *
             * @example 1
             */
            'quantity' => 'nullable|integer|min:1',

            /**
             * Bypass stock check if recipes are insufficient.
             *
             * @var bool|null
             */
            'without_recipe' => 'sometimes|nullable|boolean',

            /**
             * Special item notes.
             *
             * @var string|null
             */
            'notes' => 'nullable|string',

            /**
             * Switch to cancel / bypass all taxes (tax_id & business_setup.dinein_tax). Default is false.
             *
             * @var bool|null
             *
             * @example false
             */
            'without_tax' => 'sometimes|nullable',
            'no_tax' => 'sometimes|nullable',
            'cancel_tax' => 'sometimes|nullable',
            'exclude_tax' => 'sometimes|nullable',

            /**
             * Discount ID to apply to this item or cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'discount_id' => 'sometimes|nullable|integer|exists:discounts,id',

            /**
             * Variations.
             *
             * @var array|null
             */
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',

            /**
             * Addons.
             *
             * @var array|null
             */
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
        ]);

        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;
        if ($validated['module'] === 'dinein' && ! $tableId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد الطاولة لإضافة الطلب المحلي (طاولة)',
            ], 422);
        }

        $cashier = Cashier::find($cashierId);
        $branchId = $cashier?->branch_id ?? auth()->user()?->branch_id;

        $businessSetup = BusinessSetup::first();
        $hasGift = (bool) ($businessSetup?->has_gift ?? false);
        $priceGift = (int) ($businessSetup?->price_gift ?? 1);

        // Check stock of recipe ingredients unless without_recipe is true
        $withoutRecipe = $request->boolean('without_recipe');
        if (! $withoutRecipe && $branchId) {
            $combinationKey = null;
            if (! empty($validated['variations'])) {
                $allOptionIds = collect($validated['variations'])
                    ->flatMap(fn ($v) => $v['option_ids'] ?? [])
                    ->all();
                $combinationKey = ProductManufacturingService::buildCombinationKey($allOptionIds);
            }

            $requestedQty = max(1, (int) ($validated['quantity'] ?? 1)) * ($hasGift ? 2 : 1);
            $stockError = ProductManufacturingService::checkStockAvailability(
                $validated['product_id'],
                $requestedQty,
                $branchId,
                $combinationKey
            );

            if ($stockError) {
                return response()->json($stockError, 422);
            }
        }

        $locale = $this->getLocale($request);

        $scopeKey = $this->getCartScopeKey($validated['module'], $tableId, $cashierId);

        $discountId = null;
        if (array_key_exists('discount_id', $validated)) {
            $discountId = $validated['discount_id'] ? (int) $validated['discount_id'] : null;
        } else {
            $existingDiscountId = OrderCart::where('module', $validated['module'])
                ->when($validated['module'] === 'dinein', function ($q) use ($tableId) {
                    $q->where('hall_table_id', $tableId);
                }, function ($q) use ($cashierId) {
                    $q->where(function ($sub) use ($cashierId) {
                        $sub->where('cashier_id', $cashierId)->orWhereNull('cashier_id');
                    });
                })
                ->whereNotNull('discount_id')
                ->value('discount_id');

            $discountId = $existingDiscountId ?? Cache::get($scopeKey);
        }

        if ($discountId) {
            Cache::put($scopeKey, $discountId, now()->addHours(12));
        }

        $withoutTax = $this->resolveWithoutTax($request, $validated['module'], $tableId, $cashierId);

        $cartDineinTax = (! $withoutTax && $validated['module'] === 'dinein')
            ? (float) ($businessSetup?->dinein_tax ?? 0.0)
            : 0.0;

        $cart = DB::transaction(function () use ($validated, $tableId, $cashierId, $branchId, $hasGift, $priceGift, $discountId, $cartDineinTax): OrderCart {
            $cart = OrderCart::create([
                'module' => $validated['module'],
                'product_id' => $validated['product_id'],
                'discount_id' => $discountId,
                'dinein_tax' => $cartDineinTax,
                'cashier_id' => $cashierId,
                'cashier_man_id' => auth()->id(),
                'branch_id' => $branchId,
                'hall_table_id' => $validated['module'] === 'dinein' ? $tableId : null,
                'quantity' => $validated['quantity'] ?? 1,
                'notes' => $validated['notes'] ?? null,
                'price' => null,
                'is_gift' => false,
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

            if ($hasGift) {
                $giftCart = OrderCart::create([
                    'module' => $validated['module'],
                    'product_id' => $validated['product_id'],
                    'cashier_id' => $cashierId,
                    'cashier_man_id' => auth()->id(),
                    'branch_id' => $branchId,
                    'hall_table_id' => $validated['module'] === 'dinein' ? $tableId : null,
                    'quantity' => $validated['quantity'] ?? 1,
                    'notes' => $validated['notes'] ?? null,
                    'price' => $priceGift,
                    'dinein_tax' => $cartDineinTax,
                    'is_gift' => true,
                    'parent_id' => $cart->id,
                ]);

                foreach ($validated['variations'] ?? [] as $varData) {
                    OrderVariationCart::create([
                        'order_cart_id' => $giftCart->id,
                        'variation_id' => $varData['variation_id'],
                        'option_ids' => $varData['option_ids'],
                    ]);
                }

                foreach ($validated['addons'] ?? [] as $addonData) {
                    OrderAddonCart::create([
                        'order_cart_id' => $giftCart->id,
                        'addon_id' => $addonData['addon_id'],
                    ]);
                }

                if ($giftCart->module === 'dinein') {
                    app(KitchenDispatchService::class)->dispatchFromCart($giftCart);
                    ProductManufacturingService::deductStockForCartItem($giftCart, $branchId);
                }
            }

            return $cart;
        });

        $cart->load([
            'product.discount',
            'product.tax',
            'discount',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale, null, $withoutTax);

        if ($cart->module === 'dinein') {
            app(KitchenDispatchService::class)->dispatchFromCart($cart);
            ProductManufacturingService::deductStockForCartItem($cart, $branchId);
        }

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح',
            'without_tax' => $withoutTax,
            'data' => $calculated,
        ], 201);
    }

    /**
     * Show single cart item with calculations.
     */
    public function show(Request $request, OrderCart $cart): JsonResponse
    {
        $validated = $request->validate([
            'without_tax' => 'sometimes|nullable',
            'no_tax' => 'sometimes|nullable',
            'cancel_tax' => 'sometimes|nullable',
            'exclude_tax' => 'sometimes|nullable',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);
        $cashierId = auth()->user()?->cashier_id;
        $withoutTax = $this->resolveWithoutTax($request, $cart->module, $cart->hall_table_id, $cashierId);

        $cart->load([
            'product.discount',
            'product.tax',
            'discount',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale, null, $withoutTax);

        return response()->json([
            'status' => true,
            'without_tax' => $withoutTax,
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
            'discount_id' => 'sometimes|nullable|integer|exists:discounts,id',
            'without_tax' => 'sometimes|nullable',
            'no_tax' => 'sometimes|nullable',
            'cancel_tax' => 'sometimes|nullable',
            'exclude_tax' => 'sometimes|nullable',
            'variations' => 'nullable|array',
            'variations.*.variation_id' => 'required_with:variations|exists:variations,id',
            'variations.*.option_ids' => 'required_with:variations|array',
            'variations.*.option_ids.*' => 'exists:options,id',
            'addons' => 'nullable|array',
            'addons.*.addon_id' => 'required_with:addons|exists:addons,id',
        ]);

        $locale = $this->getLocale($request);
        $cashierId = auth()->user()?->cashier_id;
        $withoutTax = $this->resolveWithoutTax($request, $validated['module'] ?? $cart->module, $cart->hall_table_id, $cashierId);

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
            if (array_key_exists('discount_id', $validated)) {
                $updateData['discount_id'] = $validated['discount_id'];
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

            $gift = OrderCart::where('parent_id', $cart->id)->first();
            if ($gift) {
                if (! empty($updateData)) {
                    $gift->update($updateData);
                }

                if (array_key_exists('variations', $validated)) {
                    $gift->variationCarts()->delete();
                    foreach ($validated['variations'] ?? [] as $varData) {
                        OrderVariationCart::create([
                            'order_cart_id' => $gift->id,
                            'variation_id' => $varData['variation_id'],
                            'option_ids' => $varData['option_ids'],
                        ]);
                    }
                }

                if (array_key_exists('addons', $validated)) {
                    $gift->addonCarts()->delete();
                    foreach ($validated['addons'] ?? [] as $addonData) {
                        OrderAddonCart::create([
                            'order_cart_id' => $gift->id,
                            'addon_id' => $addonData['addon_id'],
                        ]);
                    }
                }
            }
        });

        $cart->load([
            'product.discount',
            'product.tax',
            'discount',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ]);

        $calculated = $this->priceCalculator->calculateCartItem($cart->fresh(), $locale, null, $withoutTax);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث عنصر السلة بنجاح',
            'without_tax' => $withoutTax,
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

        $validated = $request->validate([
            /**
             * Order module enum.
             *
             * @var string|null
             *
             * @example takeaway
             */
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',

            /**
             * Hall Table ID for dinein cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Hall Table ID alias.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',
        ]);

        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;

        $query = OrderCart::query();
        if (! empty($tableId)) {
            $query->where('hall_table_id', $tableId);
        } else {
            $query->where('cashier_id', $cashierId);
        }

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
            $scopeKey = $this->getCartScopeKey($validated['module'], $tableId, $cashierId);
            Cache::forget($scopeKey);
            Cache::forget($this->getTaxScopeKey($validated['module'], $tableId, $cashierId));
        }

        $query->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
        ]);
    }

    /**
     * Set or toggle without_tax switch for the current cart scope.
     */
    public function setTaxSwitch(Request $request): JsonResponse
    {
        $cashierId = auth()->user()?->cashier_id;
        if (! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد جهاز الكاشير أولاً',
            ], 400);
        }

        $validated = $request->validate([
            /**
             * Order module enum.
             *
             * @var string
             *
             * @example takeaway
             */
            'module' => 'required|in:takeaway,dinein,delivery',

            /**
             * Hall Table ID for dinein cart.
             *
             * @var int|null
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Switch value: true to cancel taxes, false to apply taxes. Default is false.
             *
             * @var bool|null
             *
             * @example true
             */
            'without_tax' => 'sometimes|nullable',
            'no_tax' => 'sometimes|nullable',
            'cancel_tax' => 'sometimes|nullable',
            'exclude_tax' => 'sometimes|nullable',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);
        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;
        $withoutTax = $this->resolveWithoutTax($request, $validated['module'], $tableId, $cashierId);

        $query = $this->cartQuery($cashierId, $validated['module'] === 'dinein' ? $tableId : null)
            ->where('module', $validated['module']);

        $carts = $query->orderByRaw('COALESCE(parent_id, id) ASC, id ASC')->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale, null, $withoutTax)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems, $withoutTax);

        return response()->json([
            'status' => true,
            'message' => $withoutTax ? 'تم إلغاء الضريبة بنجاح' : 'تم تفعيل الضريبة بنجاح',
            'without_tax' => $withoutTax,
            'data' => $calculatedItems,
            'grand_totals' => $grandTotals,
        ]);
    }

    /**
     * Fetch active discounts for selection in cashier cart.
     */
    public function discounts(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Response language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $discounts = Discount::where('status', true)
            ->get()
            ->map(function (Discount $discount) use ($locale) {
                $name = is_array($discount->name)
                    ? ($discount->name[$locale] ?? $discount->name['ar'] ?? $discount->name['en'] ?? '')
                    : $discount->name;

                return [
                    'id' => $discount->id,
                    'name' => $name,
                    'raw_name' => $discount->name,
                    'type' => $discount->type,
                    'amount' => (float) $discount->amount,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $discounts,
        ]);
    }

    /**
     * Apply or remove a discount for the current cart (by module and table_id or cashier_id).
     */
    public function applyDiscount(Request $request): JsonResponse
    {
        $cashierId = auth()->user()?->cashier_id;
        if (! $cashierId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد جهاز الكاشير أولاً',
            ], 400);
        }

        $validated = $request->validate([
            /**
             * Order module enum.
             *
             * @var string
             *
             * @example takeaway
             */
            'module' => 'required|in:takeaway,dinein,delivery',

            /**
             * Hall Table ID for dinein cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Hall Table ID alias for dinein cart.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Discount ID to apply to the cart. Pass null or omit to remove discount.
             *
             * @var int|null
             *
             * @example 2
             */
            'discount_id' => 'nullable|integer|exists:discounts,id',

            /**
             * Response language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);
        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;

        if ($validated['module'] === 'dinein' && ! $tableId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد الطاولة لتطبيق الخصم على الطلب المحلي',
            ], 422);
        }

        $scopeKey = $this->getCartScopeKey($validated['module'], $tableId, $cashierId);
        $discountId = $validated['discount_id'] ?? null;

        if ($discountId) {
            $discount = Discount::where('id', $discountId)->where('status', true)->first();
            if (! $discount) {
                return response()->json([
                    'status' => false,
                    'message' => 'الخصم المحدد غير متاح أو معطل حالياً',
                ], 422);
            }
            Cache::put($scopeKey, $discountId, now()->addHours(12));
        } else {
            Cache::forget($scopeKey);
        }

        // Update all current items in this cart
        $this->cartQuery($cashierId, $validated['module'] === 'dinein' ? $tableId : null)
            ->where('module', $validated['module'])
            ->update(['discount_id' => $discountId]);

        // Fetch refreshed cart items and totals
        $carts = $this->cartQuery($cashierId, $validated['module'] === 'dinein' ? $tableId : null)
            ->where('module', $validated['module'])
            ->orderByRaw('COALESCE(parent_id, id) ASC, id ASC')
            ->get();

        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart, $locale)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        return response()->json([
            'status' => true,
            'message' => $discountId ? 'تم تطبيق الخصم على السلة بنجاح' : 'تم إلغاء الخصم من السلة بنجاح',
            'applied_discount_id' => $discountId,
            'data' => $calculatedItems,
            'grand_totals' => $grandTotals,
        ]);
    }
}
