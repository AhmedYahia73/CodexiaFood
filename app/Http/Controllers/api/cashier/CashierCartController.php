<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetup;
use App\Models\Cashier;
use App\Models\Discount;
use App\Models\MaterialStock;
use App\Models\OrderAddonCart;
use App\Models\OrderCart;
use App\Models\OrderVariationCart;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipeStock;
use App\Services\KitchenDispatchService;
use App\Services\PriceCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    private function getCartScopeKey(string $module, ?int $tableId, ?int $cashierId): string
    {
        if ($module === 'dinein') {
            return "cashier_cart_discount:dinein:table_{$tableId}";
        }

        return "cashier_cart_discount:{$module}:cashier_{$cashierId}";
    }

    private function cartQuery(?int $cashierId, ?int $tableId = null): Builder
    {
        return OrderCart::with([
            'hallTable.hall',
            'product.discount',
            'product.tax',
            'discount',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])->when($tableId, function ($query, $tableId) {
            // عند تحديد الطاولة: جلب كافة عناصر سلة الطاولة بغض النظر عن الكاشير
            $query->where('hall_table_id', $tableId);
        }, function ($query) use ($cashierId) {
            // في حالة عدم تحديد طاولة (سفري / دليفري): الفلترة حسب الكاشير الحالي
            $query->where(function ($q) use ($cashierId) {
                $q->where('cashier_id', $cashierId)
                    ->orWhereNull('cashier_id');
            });
        });
    }

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
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_final_price' => 0.00,
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
        ]);

        $locale = $this->getLocale($request);
        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;

        if ($validated['module'] === 'dinein' && ! $tableId) {
            return response()->json([
                'status' => true,
                'data' => [],
                'grand_totals' => [
                    'grand_total_price' => 0.00,
                    'grand_total_discount' => 0.00,
                    'grand_total_tax' => 0.00,
                    'grand_final_price' => 0.00,
                ],
            ]);
        }

        $query = $this->cartQuery($cashierId, $validated['module'] === 'dinein' ? $tableId : null)
            ->where('module', $validated['module']);

        $carts = $query->orderByRaw('COALESCE(parent_id, id) ASC, id ASC')->get();

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
            $spec = ProductManufacturing::with([
                'productRecipeManufacturings.material',
                'productRecipeManufacturings.productRecipe',
            ])
                ->where('product_id', $validated['product_id'])
                ->latest('id')
                ->first();

            if ($spec && $spec->productRecipeManufacturings->isNotEmpty()) {
                $requestedQty = max(1, (int) ($validated['quantity'] ?? 1)) * ($hasGift ? 2 : 1);

                foreach ($spec->productRecipeManufacturings as $recipeItem) {
                    $requiredQty = (float) $recipeItem->count * $requestedQty;
                    if ($requiredQty <= 0) {
                        continue;
                    }

                    if (! empty($recipeItem->material_id)) {
                        $currentStock = (float) (MaterialStock::where('material_id', $recipeItem->material_id)
                            ->where('branch_id', $branchId)
                            ->value('stock') ?? 0);

                        if ($currentStock < $requiredQty) {
                            $matName = $recipeItem->material?->name;
                            $name = is_array($matName)
                                ? ($matName['ar'] ?? $matName['en'] ?? "مادة خام #{$recipeItem->material_id}")
                                : ($matName ?? "مادة خام #{$recipeItem->material_id}");

                            return response()->json([
                                'status' => false,
                                'message' => "المخزون المتوفر للمادة الخام ({$name}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                                'insufficient_ingredient' => [
                                    'type' => 'material',
                                    'id' => $recipeItem->material_id,
                                    'name' => $name,
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
                            $recName = $recipeItem->productRecipe?->name;
                            $name = is_array($recName)
                                ? ($recName['ar'] ?? $recName['en'] ?? "وصفة #{$recipeItem->product_recipe_id}")
                                : ($recName ?? "وصفة #{$recipeItem->product_recipe_id}");

                            return response()->json([
                                'status' => false,
                                'message' => "المخزون المتوفر للوصفة ({$name}) في هذا الفرع غير كافٍ. المتاح: {$currentStock}، المطلوب: {$requiredQty}.",
                                'insufficient_ingredient' => [
                                    'type' => 'product_recipe',
                                    'id' => $recipeItem->product_recipe_id,
                                    'name' => $name,
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

        $cart = DB::transaction(function () use ($validated, $tableId, $cashierId, $branchId, $hasGift, $priceGift, $discountId): OrderCart {
            $cart = OrderCart::create([
                'module' => $validated['module'],
                'product_id' => $validated['product_id'],
                'discount_id' => $discountId,
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

        $calculated = $this->priceCalculator->calculateCartItem($cart, $locale);

        if ($cart->module === 'dinein') {
            app(KitchenDispatchService::class)->dispatchFromCart($cart);
        }

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
            'discount',
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
            'discount_id' => 'sometimes|nullable|integer|exists:discounts,id',
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
        }

        $query->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
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
