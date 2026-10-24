<?php

namespace App\Http\Controllers\api\kitchen;

use App\Http\Controllers\Controller;
use App\Models\KitchenOrder;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenOrderController extends Controller
{
    /**
     * Display all kitchen orders assigned to the authenticated kitchen.
     * Returns items with product/addon names and option names according to lang.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lang' => 'required|string|in:ar,en',
        ]);

        $lang = $validated['lang'];
        $kitchenId = auth('kitchen')->id() ?? auth()->id();

        $kitchenOrders = KitchenOrder::with([
            'items.product',
            'items.addon',
            'items.details',
            'order.hallTable',
            'orderCart.hallTable',
        ])
            ->where('kitchen_id', $kitchenId)
            ->latest('id')
            ->get();

        $data = $this->formatKitchenOrders($kitchenOrders, $lang);

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * Format a collection of KitchenOrder models into a standardized array.
     */
    private function formatKitchenOrders($kitchenOrders, string $lang): array
    {
        $allOptionIds = [];
        foreach ($kitchenOrders as $ko) {
            foreach ($ko->items as $item) {
                foreach ($item->details as $detail) {
                    if (is_array($detail->option_ids)) {
                        foreach ($detail->option_ids as $optId) {
                            $allOptionIds[] = (int) $optId;
                        }
                    }
                }
            }
        }

        $optionsMap = empty($allOptionIds)
            ? collect()
            : Option::whereIn('id', array_unique($allOptionIds))->get()->keyBy('id');

        return $kitchenOrders->map(function (KitchenOrder $kitchenOrder) use ($lang, $optionsMap): array {
            $table = $kitchenOrder->order?->hallTable ?? $kitchenOrder->orderCart?->hallTable;

            return [
                'id' => $kitchenOrder->id,
                'order_id' => $kitchenOrder->order_id,
                'order_cart_id' => $kitchenOrder->order_cart_id,
                'table' => $table ? [
                    'id' => $table->id,
                    'code' => $table->code,
                    'name' => $table->name,
                ] : null,
                'items' => $kitchenOrder->items->map(function ($item) use ($lang, $optionsMap): array {
                    $productName = is_array($item->product?->name)
                        ? ($item->product->name[$lang] ?? $item->product->name['ar'] ?? $item->product->name['en'] ?? '')
                        : ($item->product?->name ?? '');

                    $addonName = null;
                    if ($item->addon) {
                        $addonName = is_array($item->addon->name)
                            ? ($item->addon->name[$lang] ?? $item->addon->name['ar'] ?? $item->addon->name['en'] ?? '')
                            : $item->addon->name;
                    }

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $productName,
                        'addon_id' => $item->addon_id,
                        'addon_name' => $addonName,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                        'details' => $item->details->map(function ($detail) use ($lang, $optionsMap): array {
                            $optionsList = [];
                            $optionNames = [];
                            if (is_array($detail->option_ids)) {
                                foreach ($detail->option_ids as $optId) {
                                    $opt = $optionsMap->get((int) $optId);
                                    if ($opt) {
                                        $optName = is_array($opt->name)
                                            ? ($opt->name[$lang] ?? $opt->name['ar'] ?? $opt->name['en'] ?? '')
                                            : $opt->name;
                                        $optionsList[] = [
                                            'id' => $opt->id,
                                            'name' => $optName,
                                        ];
                                        $optionNames[] = $optName;
                                    }
                                }
                            }

                            return [
                                'id' => $detail->id,
                                'option_ids' => $detail->option_ids,
                                'option_names' => $optionNames,
                                'options' => $optionsList,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Mark the kitchen order as done.
     *
     * Deletes this kitchen's KitchenOrder for the given order/cart.
     * If no other kitchens have pending KitchenOrders for the same order/cart,
     * the order or cart status is automatically changed to 'done'.
     */
    public function markDone(Request $request, KitchenOrder|int|string $kitchenOrder): JsonResponse
    {
        if (! ($kitchenOrder instanceof KitchenOrder)) {
            $kitchenOrder = KitchenOrder::findOrFail((int) $kitchenOrder);
        }

        $authKitchenId = auth('kitchen')->id() ?? auth()->id();

        if ((int) $kitchenOrder->kitchen_id !== (int) $authKitchenId) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: This order belongs to another kitchen.',
            ], 403);
        }

        $orderId = $kitchenOrder->order_id;
        $orderCartId = $kitchenOrder->order_cart_id;

        // Check if other kitchens are still preparing items for the same order/cart
        $otherKitchensCount = KitchenOrder::where('id', '!=', $kitchenOrder->id)
            ->when($orderId, fn ($q) => $q->where('order_id', $orderId))
            ->when($orderCartId, fn ($q) => $q->where('order_cart_id', $orderCartId))
            ->count();

        // If no other kitchens are preparing, transition the order / cart status to 'done'
        if ($otherKitchensCount === 0) {
            if ($orderId) {
                $order = Order::find($orderId);
                $order?->update(['status' => 'done']);
            } elseif ($orderCartId) {
                $cart = OrderCart::find($orderCartId);
                $cart?->update(['status' => 'done']);
            }
        }

        // Always delete auth kitchen's KitchenOrder record if not already removed by observer
        if ($kitchenOrder->exists) {
            $kitchenOrder->delete();
        }

        return response()->json([
            'status' => true,
            'message' => $otherKitchensCount === 0
                ? 'Order marked as done. All kitchens completed.'
                : 'Your kitchen order is finished. Waiting for other kitchens to complete.',
        ]);
    }

    /**
     * Display orders and cart items finished in the kitchen (status = 'done').
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
             * Filter orders by module enum.
             *
             * @var string|null
             *
             * @example dinein
             */
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',

            /**
             * Filter by captain order ID.
             *
             * @var int|null
             *
             * @example 1
             */
            'captain_order_id' => 'sometimes|nullable|integer|exists:captain_orders,id',

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

        $lang = $validated['lang']
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? 'ar';
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

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

        if (! empty($validated['date'])) {
            $ordersQuery->whereDate('updated_at', $validated['date']);
        }
        if (! empty($validated['table_id'])) {
            $ordersQuery->where('hall_table_id', $validated['table_id']);
        }
        if (! empty($validated['module'])) {
            $ordersQuery->where('module', $validated['module']);
        }
        if (! empty($validated['captain_order_id'])) {
            $ordersQuery->where('captain_order_id', $validated['captain_order_id']);
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

        // Also fetch active dinein cart items that are completed in kitchen before cashier checkout
        $cartsQuery = OrderCart::with([
            'hallTable.hall',
            'captainOrder',
            'product',
            'variationCarts.variation',
            'addonCarts.addon',
        ])
            ->where('status', 'done')
            ->latest('updated_at');

        if (! empty($validated['date'])) {
            $cartsQuery->whereDate('updated_at', $validated['date']);
        }
        if (! empty($validated['table_id'])) {
            $cartsQuery->where('hall_table_id', $validated['table_id']);
        }
        if (! empty($validated['captain_order_id'])) {
            $cartsQuery->where('captain_order_id', $validated['captain_order_id']);
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
     * Return orders difference and new orders for kitchen notifications.
     */
    public function checkNewOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Current count of orders on the client side.
             *
             * @var int
             *
             * @example 5
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

        $kitchenId = auth('kitchen')->id() ?? auth()->id();
        $lang = $validated['lang'] ?? 'ar';

        $serverCount = KitchenOrder::where('kitchen_id', $kitchenId)->count();
        $difference = max(0, $serverCount - (int) $validated['count']);

        $newOrders = [];
        if ($difference > 0) {
            $newKitchenOrders = KitchenOrder::with([
                'items.product',
                'items.addon',
                'items.details',
                'order.hallTable',
                'orderCart.hallTable',
            ])
                ->where('kitchen_id', $kitchenId)
                ->latest('id')
                ->take($difference)
                ->get();

            $newOrders = $this->formatKitchenOrders($newKitchenOrders, $lang);
        }

        return response()->json([
            'status' => true,
            'difference' => $difference,
            'new_orders' => $newOrders,
        ]);
    }
}
