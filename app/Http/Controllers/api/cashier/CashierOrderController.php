<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\StartShift;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CashierOrderController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator
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
            'module' => 'required|in:takeaway,dinein,delivery',
            'hall_table_id' => 'required_if:module,dinein|nullable|exists:hall_tables,id',
            'address' => 'required_if:module,delivery|nullable|string',
            'phone' => 'required_if:module,delivery|nullable|string|max:50',
            'name' => 'required_if:module,delivery|nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        // Fetch cart items for current cashier and module
        $carts = OrderCart::with([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])
            ->where('cashier_id', $cashierId)
            ->where('module', $validated['module'])
            ->get();

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

        $order = DB::transaction(function () use ($validated, $carts, $grandTotals, $cashierId, $openShiftId): Order {
            $order = Order::create([
                'shift_id' => $openShiftId,
                'cashier_id' => $cashierId,
                'cashier_man_id' => auth()->id(),
                'hall_table_id' => $validated['hall_table_id'] ?? null,
                'module' => $validated['module'],
                'address' => $validated['address'] ?? null,
                'note' => $validated['note'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'name' => $validated['name'] ?? null,
                'is_pos' => true,
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
            }

            // Clear checked out cart items
            OrderCart::where('cashier_id', $cashierId)
                ->where('module', $validated['module'])
                ->delete();

            return $order;
        });

        $order->load([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح من السلة',
            'data' => new OrderResource($order),
        ], 201);
    }
}
