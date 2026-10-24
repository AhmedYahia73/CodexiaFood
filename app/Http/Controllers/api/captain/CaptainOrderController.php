<?php

namespace App\Http\Controllers\api\captain;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Captain Orders
 */
class CaptainOrderController extends Controller
{
    private function getLocale(Request $request): string
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();

        return str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
    }

    /**
     * Display orders and cart items finished in the kitchen (status = 'done') for captains to serve.
     */
    public function doneOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',

            /**
             * Filter by specific business date (YYYY-MM-DD).
             *
             * @var string|null
             *
             * @example "2026-10-24"
             */
            'date' => 'sometimes|nullable|string',

            /**
             * Filter by hall table ID.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Only show orders/carts handled by this authenticated captain.
             *
             * @var bool|null
             *
             * @example true
             */
            'my_orders_only' => 'sometimes|nullable|boolean',

            /**
             * Page number for pagination.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $locale = $this->getLocale($request);
        $captainId = auth('captain_order')->id();

        $localize = function (mixed $value) use ($locale): ?string {
            if (is_array($value)) {
                return $value[$locale] ?? $value['ar'] ?? $value['en'] ?? null;
            }

            return is_string($value) ? $value : null;
        };

        $ordersQuery = Order::with([
            'hallTable.hall',
            'captainOrder',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ])
            ->where('status', 'done')
            ->latest('updated_at');

        if (! empty($validated['my_orders_only']) && $captainId) {
            $ordersQuery->where('captain_order_id', $captainId);
        }

        if (! empty($validated['table_id'])) {
            $ordersQuery->where('hall_table_id', $validated['table_id']);
        }

        if (! empty($validated['date'])) {
            $ordersQuery->whereDate('updated_at', $validated['date']);
        }

        $doneOrders = $ordersQuery->get()->map(function (Order $order) use ($localize) {
            return [
                'type' => 'order',
                'id' => $order->id,
                'order_id' => $order->id,
                'module' => $order->module,
                'status' => $order->status,
                'table' => $order->hallTable ? [
                    'id' => $order->hallTable->id,
                    'code' => $order->hallTable->code,
                    'name' => $order->hallTable->name,
                    'hall_name' => $localize($order->hallTable->hall?->name),
                ] : null,
                'captain_order' => $order->captainOrder ? [
                    'id' => $order->captainOrder->id,
                    'name' => $order->captainOrder->name,
                    'user_name' => $order->captainOrder->user_name,
                ] : null,
                'items' => $order->orderProducts->map(function ($op) use ($localize) {
                    return [
                        'id' => $op->id,
                        'product_id' => $op->product_id,
                        'product_name' => $localize($op->product?->name),
                        'quantity' => $op->quantity,
                        'price' => (float) $op->price,
                        'note' => $op->note,
                        'variations' => $op->variations->map(fn ($v) => [
                            'name' => $localize($v->variation?->name),
                            'options' => $v->options->map(fn ($o) => $localize($o->option?->name))->filter()->values(),
                        ])->values(),
                        'addons' => $op->addons->map(fn ($a) => [
                            'name' => $localize($a->addon?->name),
                            'price' => (float) $a->price,
                        ])->values(),
                    ];
                })->values(),
                'total' => (float) $order->final_price,
                'completed_at' => $order->updated_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
            ];
        });

        // Also fetch active dinein cart items completed in kitchen
        $cartsQuery = OrderCart::with([
            'hallTable.hall',
            'captainOrder',
            'product',
            'variationCarts.variation',
            'addonCarts.addon',
        ])
            ->where('status', 'done')
            ->latest('updated_at');

        if (! empty($validated['my_orders_only']) && $captainId) {
            $cartsQuery->where('captain_order_id', $captainId);
        }

        if (! empty($validated['table_id'])) {
            $cartsQuery->where('hall_table_id', $validated['table_id']);
        }

        if (! empty($validated['date'])) {
            $cartsQuery->whereDate('updated_at', $validated['date']);
        }

        $doneCarts = $cartsQuery->get()->map(function (OrderCart $cart) use ($localize) {
            return [
                'type' => 'cart_item',
                'id' => $cart->id,
                'order_cart_id' => $cart->id,
                'module' => $cart->module,
                'status' => $cart->status,
                'table' => $cart->hallTable ? [
                    'id' => $cart->hallTable->id,
                    'code' => $cart->hallTable->code,
                    'name' => $cart->hallTable->name,
                    'hall_name' => $localize($cart->hallTable->hall?->name),
                ] : null,
                'captain_order' => $cart->captainOrder ? [
                    'id' => $cart->captainOrder->id,
                    'name' => $cart->captainOrder->name,
                    'user_name' => $cart->captainOrder->user_name,
                ] : null,
                'items' => [
                    [
                        'id' => $cart->id,
                        'product_id' => $cart->product_id,
                        'product_name' => $localize($cart->product?->name),
                        'quantity' => $cart->quantity,
                        'price' => (float) ($cart->price ?? $cart->product?->price),
                        'note' => $cart->notes,
                        'variations' => $cart->variationCarts->map(fn ($v) => [
                            'name' => $localize($v->variation?->name),
                            'options' => $v->getOptions()->map(fn ($o) => $localize($o->name))->filter()->values(),
                        ])->values(),
                        'addons' => $cart->addonCarts->map(fn ($a) => [
                            'name' => $localize($a->addon?->name),
                            'price' => (float) ($a->addon?->price ?? 0),
                        ])->values(),
                    ],
                ],
                'completed_at' => $cart->updated_at?->toIso8601String(),
                'created_at' => $cart->created_at?->toIso8601String(),
            ];
        });

        $allDone = $doneOrders->concat($doneCarts)->sortByDesc('completed_at')->values();

        return response()->json([
            'status' => true,
            'data' => $allDone,
            'count' => $allDone->count(),
        ]);
    }

    /**
     * List dine-in orders with status and table details.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Filter by specific status.
             *
             * @var string|null
             *
             * @example done
             */
            'status' => 'sometimes|nullable|string',

            /**
             * Filter by hall table ID.
             *
             * @var int|null
             *
             * @example 1
             */
            'table_id' => 'sometimes|nullable|integer|exists:hall_tables,id',

            /**
             * Only show orders handled by current captain.
             *
             * @var bool|null
             *
             * @example true
             */
            'my_orders_only' => 'sometimes|nullable|boolean',

            /**
             * Page number for pagination.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',

            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $captainId = auth('captain_order')->id();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $orders = Order::with([
            'hallTable.hall',
            'captainOrder',
            'orderProducts.product',
        ])
            ->where('module', 'dinein')
            ->when(! empty($validated['my_orders_only']) && $captainId, fn ($q) => $q->where('captain_order_id', $captainId))
            ->when(! empty($validated['table_id']), fn ($q) => $q->where('hall_table_id', $validated['table_id']))
            ->when(! empty($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Show single order details.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $order->loadMissing([
            'hallTable.hall',
            'captainOrder',
            'orderProducts.product',
            'orderProducts.variations.options',
            'orderProducts.addons.addon',
        ]);

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Return orders difference and new ready orders for captain notifications.
     */
    public function checkNewOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Current count of orders on the client side.
             *
             * @var int
             *
             * @example 3
             */
            'count' => 'required|integer|min:0',

            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $ordersQuery = Order::with([
            'hallTable.hall',
            'orderProducts.product',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ])
            ->where('status', 'done')
            ->latest('updated_at');

        $cartsQuery = OrderCart::with([
            'hallTable.hall',
            'product',
            'variationCarts.variation',
            'addonCarts.addon',
        ])
            ->where('status', 'done')
            ->latest('updated_at');

        $serverCount = $ordersQuery->count() + $cartsQuery->count();
        $difference = max(0, $serverCount - (int) $validated['count']);

        $newOrders = [];
        if ($difference > 0) {
            $localize = function (mixed $value) use ($locale): ?string {
                if (is_array($value)) {
                    return $value[$locale] ?? $value['ar'] ?? $value['en'] ?? null;
                }

                return is_string($value) ? $value : null;
            };

            $doneOrders = $ordersQuery->take($difference)->get()->map(function (Order $order) use ($localize) {
                return [
                    'type' => 'order',
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'table' => $order->hallTable ? [
                        'id' => $order->hallTable->id,
                        'code' => $order->hallTable->code,
                        'name' => $order->hallTable->name,
                        'hall_name' => $localize($order->hallTable->hall?->name),
                    ] : null,
                    'items' => $order->orderProducts->map(function ($op) use ($localize) {
                        return [
                            'id' => $op->id,
                            'product_name' => $localize($op->product?->name),
                            'quantity' => $op->quantity,
                            'note' => $op->note,
                            'variations' => $op->variations->map(fn ($v) => [
                                'name' => $localize($v->variation?->name),
                                'options' => $v->options->map(fn ($o) => $localize($o->option?->name))->filter()->values(),
                            ])->values(),
                            'addons' => $op->addons->map(fn ($a) => [
                                'name' => $localize($a->addon?->name),
                                'price' => (float) $a->price,
                            ])->values(),
                        ];
                    })->values(),
                    'completed_at' => $order->updated_at?->toIso8601String(),
                ];
            });

            $doneCarts = $cartsQuery->take($difference)->get()->map(function (OrderCart $cart) use ($localize) {
                return [
                    'type' => 'cart_item',
                    'id' => $cart->id,
                    'order_cart_id' => $cart->id,
                    'table' => $cart->hallTable ? [
                        'id' => $cart->hallTable->id,
                        'code' => $cart->hallTable->code,
                        'name' => $cart->hallTable->name,
                        'hall_name' => $localize($cart->hallTable->hall?->name),
                    ] : null,
                    'items' => [
                        [
                            'id' => $cart->id,
                            'product_name' => $localize($cart->product?->name),
                            'quantity' => $cart->quantity,
                            'note' => $cart->notes,
                            'variations' => $cart->variationCarts->map(fn ($v) => [
                                'name' => $localize($v->variation?->name),
                                'options' => $v->getOptions()->map(fn ($o) => $localize($o->name))->filter()->values(),
                            ])->values(),
                            'addons' => $cart->addonCarts->map(fn ($a) => [
                                'name' => $localize($a->addon?->name),
                                'price' => (float) ($a->addon?->price ?? 0),
                            ])->values(),
                        ],
                    ],
                    'completed_at' => $cart->updated_at?->toIso8601String(),
                ];
            });

            $newOrders = $doneOrders->concat($doneCarts)
                ->sortByDesc('completed_at')
                ->take($difference)
                ->values()
                ->all();
        }

        return response()->json([
            'status' => true,
            'difference' => $difference,
            'new_orders' => $newOrders,
        ]);
    }
}
