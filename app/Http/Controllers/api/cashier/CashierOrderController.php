<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cashier;
use App\Models\Delivery;
use App\Models\FinancialAccount;
use App\Models\MaterialStock;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipeStock;
use App\Models\StartShift;
use App\Services\KitchenDispatchService;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashierOrderController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator,
        protected KitchenDispatchService $kitchenDispatch,
    ) {}

    /**
     * List orders for the current cashier with search and filters.
     *
     * @return AnonymousResourceCollection<OrderResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Search by order ID / number, customer phone, or customer name.
             *
             * @var string
             */
            'search' => 'sometimes|nullable|string|max:255',

            /**
             * Filter directly by order ID.
             *
             * @var int
             */
            'id' => 'sometimes|nullable|integer',

            /**
             * Filter directly by order ID or number.
             *
             * @var string
             */
            'order_number' => 'sometimes|nullable|string|max:255',

            /**
             * Filter orders by module enum.
             *
             * @var string
             *
             * @example takeaway
             */
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',

            /**
             * Filter directly by customer phone number.
             *
             * @var string
             */
            'phone' => 'sometimes|nullable|string|max:50',

            /**
             * Filter directly by customer name.
             *
             * @var string
             */
            'name' => 'sometimes|nullable|string|max:255',

            /**
             * Filter orders by hall table ID.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Filter orders by hall table ID alias.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Number of items per page.
             *
             * @var int
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',

            /**
             * Page number.
             *
             * @var int
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',
        ]);

        $cashierId = auth()->user()?->cashier_id;

        $query = Order::with([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ])->when($cashierId, function ($q) use ($cashierId) {
            $q->where('cashier_id', $cashierId);
        });

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        $tableId = $validated['table_id'] ?? $validated['hall_table_id'] ?? null;
        if (! empty($tableId)) {
            $query->where('hall_table_id', $tableId);
        }

        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }

        if (! empty($validated['order_number'])) {
            $cleanOrderNum = ltrim(trim((string) $validated['order_number']), '#');
            if (is_numeric($cleanOrderNum)) {
                $query->where('id', (int) $cleanOrderNum);
            }
        }

        if (! empty($validated['phone'])) {
            $query->where('phone', 'like', "%{$validated['phone']}%");
        }

        if (! empty($validated['name'])) {
            $query->where('name', 'like', "%{$validated['name']}%");
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $cleanId = ltrim($search, '#');
            $isNumeric = is_numeric($cleanId);

            $query->where(function ($sub) use ($search, $cleanId, $isNumeric) {
                if ($isNumeric) {
                    $sub->where('id', (int) $cleanId)
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                } else {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        $orders = $query->latest('id')->paginate($perPage);

        return OrderResource::collection($orders);
    }

    /**
     * Show single order details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Checkout cart items and place an order.
     */
    public function checkout(Request $request): JsonResponse
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
            'hall_table_id' => 'sometimes|nullable|exists:hall_tables,id',

            /**
             * Hall Table ID alias.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|exists:hall_tables,id',

            /**
             * Customer delivery address.
             *
             * @var string|null
             */
            'address' => 'required_if:module,delivery|nullable|string',

            /**
             * Customer phone number.
             *
             * @var string|null
             *
             * @example "01012345678"
             */
            'phone' => 'required_if:module,delivery|nullable|string|max:50',

            /**
             * Customer name.
             *
             * @var string|null
             *
             * @example "Ahmed Ali"
             */
            'name' => 'required_if:module,delivery|nullable|string|max:255',

            /**
             * Order notes.
             *
             * @var string|null
             */
            'note' => 'nullable|string',

            /**
             * Financial Account ID for payment collection.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_account_id' => 'required_without:financial_id|nullable|integer',

            /**
             * Alias for financial_account_id.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_id' => 'sometimes|nullable|integer',

            /**
             * Delivery ID for delivery orders.
             *
             * @var int|null
             *
             * @example 1
             */
            'delivery_id' => 'sometimes|nullable|exists:deliveries,id',

            /**
             * Payment Method ID.
             *
             * @var int|null
             *
             * @example 1
             */
            'payment_method_id' => 'sometimes|nullable|exists:payment_methods,id',

            /**
             * Paid status boolean.
             *
             * @var bool|null
             *
             * @example true
             */
            'paid' => 'sometimes|nullable|boolean',
        ]);

        $financialAccountId = $validated['financial_account_id'] ?? $validated['financial_id'] ?? null;
        if (! $financialAccountId) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد الحساب المالي لإتمام الطلب',
                'errors' => [
                    'financial_account_id' => ['يرجى تحديد الحساب المالي لإتمام الطلب'],
                ],
            ], 422);
        }

        // Verify if account exists in database; if not, self-heal via fallbacks
        if (! FinancialAccount::where('id', $financialAccountId)->exists()) {
            $openShift = StartShift::where('cashier_id', $cashierId)
                ->whereNull('end')
                ->latest('start')
                ->first();

            if ($openShift?->financial_account_id && FinancialAccount::where('id', $openShift->financial_account_id)->exists()) {
                $financialAccountId = $openShift->financial_account_id;
            } else {
                $branchId = auth()->user()?->branch_id;
                $financialAccountId = FinancialAccount::query()
                    ->where('status', true)
                    ->when($branchId, fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                    ->value('id')
                    ?? FinancialAccount::where('status', true)->value('id')
                    ?? FinancialAccount::value('id');

                if (! $financialAccountId) {
                    $defaultAcc = FinancialAccount::create([
                        'name' => ['ar' => 'الخزينة النقدية الرئيسية', 'en' => 'Main Cash Treasury'],
                        'status' => true,
                        'balance' => 0.00,
                        'branch_id' => $branchId,
                    ]);
                    $financialAccountId = $defaultAcc->id;
                }
            }
        }

        $tableId = $validated['hall_table_id'] ?? $validated['table_id'] ?? null;

        // Fetch cart items for current cashier and module
        $cartsQuery = OrderCart::with([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])
            ->where('cashier_id', $cashierId)
            ->where('module', $validated['module']);

        if ($validated['module'] === 'dinein') {
            if (! $tableId) {
                return response()->json([
                    'status' => false,
                    'message' => 'يرجى تحديد الطاولة لإتمام الطلب المحلي (طاولة)',
                ], 422);
            }
            $cartsQuery->where('hall_table_id', $tableId);
        }

        $carts = $cartsQuery->get();

        if ($carts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'السلة فارغة لهذا القسم',
            ], 400);
        }

        // Calculate item totals and grand totals using PriceCalculatorService
        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        // Find active open shift for current cashier_man
        $openShiftId = StartShift::where('cashier_man_id', auth()->id())
            ->whereNull('end')
            ->latest('start')
            ->value('id');

        $cashier = Cashier::find($cashierId);
        $branchId = $cashier?->branch_id ?? auth()->user()?->branch_id;

        $paymentMethodId = $validated['payment_method_id'] ?? null;
        $isCash = false;
        if ($financialAccountId) {
            $isCash = (bool) FinancialAccount::where('id', $financialAccountId)->value('is_cash');
        }
        if ($paymentMethodId) {
            $isCash = $isCash || (bool) PaymentMethod::where('id', $paymentMethodId)->value('is_cash');
        }
        $module = $validated['module'] ?? 'takeaway';
        $isDelivery = ($module === 'delivery');

        $paid = array_key_exists('paid', $validated) && ! is_null($validated['paid'])
            ? (bool) $validated['paid']
            : (! ($isCash && $isDelivery));

        $order = DB::transaction(function () use ($validated, $tableId, $financialAccountId, $paymentMethodId, $paid, $carts, $grandTotals, $cashierId, $openShiftId, $branchId): Order {
            $order = Order::create([
                'shift_id' => $openShiftId,
                'cashier_id' => $cashierId,
                'cashier_man_id' => auth()->id(),
                'hall_table_id' => $tableId,
                'financial_account_id' => $financialAccountId,
                'delivery_id' => $validated['delivery_id'] ?? null,
                'payment_method_id' => $paymentMethodId,
                'paid' => $paid,
                'module' => $validated['module'],
                'address' => $validated['address'] ?? null,
                'note' => $validated['note'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'name' => $validated['name'] ?? null,
                'is_pos' => true,
                'status' => $validated['module'] === 'dinein' ? 'pickup' : 'preparing',
                'total' => $grandTotals['grand_total_price'],
                'total_discount' => $grandTotals['grand_total_discount'],
                'total_tax' => $grandTotals['grand_total_tax'],
                'final_price' => $grandTotals['grand_final_price'],
            ]);

            foreach ($carts as $cart) {
                $qty = max(1, (int) $cart->quantity);

                $orderProduct = OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $cart->product_id,
                    'quantity' => $qty,
                    'price' => (float) $cart->product->price,
                    'note' => $cart->notes,
                ]);

                foreach ($cart->variationCarts as $varCart) {
                    $orderVariation = OrderPVariation::create([
                        'order_product_id' => $orderProduct->id,
                        'variation_id' => $varCart->variation_id,
                    ]);

                    $options = $varCart->getOptions();
                    foreach ($options as $option) {
                        OrderPOption::create([
                            'order_p_variation_id' => $orderVariation->id,
                            'option_id' => $option->id,
                            'price' => (float) $option->price,
                        ]);
                    }
                }

                foreach ($cart->addonCarts as $addonCart) {
                    if ($addonCart->addon) {
                        OrderPAddon::create([
                            'order_product_id' => $orderProduct->id,
                            'addon_id' => $addonCart->addon_id,
                            'price' => (float) $addonCart->addon->price,
                        ]);
                    }
                }

                // Deduct from branch stock for product ingredients based on ProductManufacturing
                if ($branchId) {
                    $spec = ProductManufacturing::with([
                        'productRecipeManufacturings.material',
                        'productRecipeManufacturings.productRecipe',
                    ])
                        ->where('product_id', $cart->product_id)
                        ->latest('id')
                        ->first();

                    if ($spec) {
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
                                    // Stock must not drop below 0: if stock is 9 and recipe needs 10, deduct only 9
                                    $deduct = min(max(0.0, $current), $requiredQty);
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
                                    // Stock must not drop below 0: if stock is 9 and recipe needs 10, deduct only 9
                                    $deduct = min(max(0.0, $current), $requiredQty);
                                    if ($deduct > 0) {
                                        $recStock->decrement('stock', $deduct);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Clear checked out cart items
            OrderCart::whereIn('id', $carts->pluck('id'))->delete();

            return $order;
        });

        $order->load([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        // Dispatch to kitchen queues (dinein is handled per-cart, not at order level)
        if ($order->module !== 'dinein') {
            $this->kitchenDispatch->dispatchFromOrder($order);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح من السلة',
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * Get list of delivery drivers (id and name only) for cashier dropdowns.
     */
    public function deliveriesSelect(Request $request): JsonResponse
    {
        $branchId = auth()->user()?->branch_id;

        $deliveries = Delivery::when($branchId, function ($q) use ($branchId) {
            $q->where(function ($sub) use ($branchId) {
                $sub->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        })->select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $deliveries,
        ]);
    }

    /**
     * Update order status with module-specific allowed transitions.
     * When status is out_of_delivery and module is delivery, delivery_id is required.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $allowedStatuses = Order::$allowedStatusTransitions[$order->module] ?? [];

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $allowedStatuses)],
            'delivery_id' => [
                Rule::requiredIf(fn () => $order->module === 'delivery' && $request->input('status') === 'out_of_delivery'),
                'nullable',
                'integer',
                'exists:deliveries,id',
            ],
        ]);

        $updateData = ['status' => $validated['status']];
        if (array_key_exists('delivery_id', $validated) && ! is_null($validated['delivery_id'])) {
            $updateData['delivery_id'] = $validated['delivery_id'];
        }

        $order->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح.',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'delivery_id' => $order->delivery_id,
            ],
        ]);
    }

    /**
     * List unpaid delivery orders (module = delivery and paid = false) with pagination and delivery_id filter.
     */
    public function unpaidDeliveries(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'delivery_id' => 'sometimes|nullable|integer|exists:deliveries,id',
            'search' => 'sometimes|nullable|string|max:255',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $branchId = auth()->user()?->branch_id;
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $query = Order::with([
            'delivery',
            'shift',
            'cashier',
            'cashierMan',
            'financialAccount',
            'paymentMethod',
            'orderProducts.product',
        ])
            ->where('module', 'delivery')
            ->where('paid', false)
            ->whereNotIn('status', ['return', 'failed_to_delivered', 'faild_to_delivered'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest();

        if (! empty($validated['delivery_id'])) {
            $query->where('delivery_id', $validated['delivery_id']);
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $cleanOrderNum = ltrim($search, '#');
            $query->where(function ($sub) use ($search, $cleanOrderNum) {
                if (is_numeric($cleanOrderNum)) {
                    $sub->orWhere('id', (int) $cleanOrderNum);
                }
                $sub->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders)->additional([
            'status' => true,
        ]);
    }

    /**
     * Settle payment for a batch of delivery orders.
     * Updates: paid = true, cashier_id = auth()->cashier_id, cashier_man_id = auth()->id, shift_id = auth()->shift_id.
     */
    public function settleDeliveryOrdersPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => 'sometimes|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id', 
        ]);

        $orderIds = $validated['order_ids'] ?? [];
        if (empty($orderIds) && ! empty($validated['orders'])) {
            $raw = $validated['orders'];
            $orderIds = is_array($raw[0] ?? null)
                ? collect($raw)->pluck('id')->filter()->all()
                : $raw;
        }

        if (empty($orderIds)) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد أرقام الطلبات المراد تسوية دفعها',
            ], 422);
        }

        $cashierUser = auth()->user();
        $cashierManId = auth()->id();
        $cashierId = $cashierUser?->cashier_id;
        $branchId = $cashierUser?->branch_id;

        // Resolve active shift id
        $shiftId = StartShift::where('cashier_man_id', $cashierManId)
            ->whereNull('end')
            ->latest('start')
            ->value('shift_id')
            ?? $cashierUser?->shift_id;

        $ordersToUpdate = Order::whereIn('id', $orderIds)
            ->where('module', 'delivery')
            ->where('paid', false)
            ->whereNotIn('status', ['return', 'failed_to_delivered', 'faild_to_delivered'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        if ($ordersToUpdate->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على طلبات توصيل غير مدفوعة مطابقة للأرقام المحددة',
            ], 404);
        }

        $settledIds = [];
        $totalCollected = 0.0;

        DB::transaction(function () use ($ordersToUpdate, $cashierId, $cashierManId, $shiftId, &$settledIds, &$totalCollected) {
            foreach ($ordersToUpdate as $order) {
                $order->update([
                    'paid' => true,
                    'cashier_id' => $cashierId ?? $order->cashier_id,
                    'cashier_man_id' => $cashierManId,
                    'shift_id' => $shiftId ?? $order->shift_id,
                ]);
                $settledIds[] = $order->id;
                $totalCollected += (float) $order->final_price;
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'تم تحصيل وتسوية حساب طلبات التوصيل بنجاح',
            'data' => [
                'settled_count' => count($settledIds),
                'settled_order_ids' => $settledIds,
                'total_amount_collected' => $totalCollected,
                'cashier_id' => $cashierId,
                'cashier_man_id' => $cashierManId,
                'shift_id' => $shiftId,
            ],
        ]);
    }
}
