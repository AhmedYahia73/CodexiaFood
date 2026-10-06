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
        ])
            ->where('kitchen_id', $kitchenId)
            ->latest('id')
            ->get();

        // Collect all unique option IDs across all order items to load option names in one query
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

        $data = $kitchenOrders->map(function (KitchenOrder $kitchenOrder) use ($lang, $optionsMap): array {
            return [
                'id' => $kitchenOrder->id,
                'order_id' => $kitchenOrder->order_id,
                'order_cart_id' => $kitchenOrder->order_cart_id,
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
        });

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
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
}
