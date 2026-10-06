<?php

namespace App\Services;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderDetail;
use App\Models\KitchenOrderItem;
use App\Models\Order;
use App\Models\OrderCart;
use Illuminate\Support\Facades\DB;

/**
 * Dispatches orders to kitchen queues based on each product's and addon's kitchen_id.
 *
 * For non-dinein modules (delivery, takeaway): call dispatchFromOrder(Order $order).
 * For dinein module: call dispatchFromCart(OrderCart $cart) per cart item.
 */
class KitchenDispatchService
{
    /**
     * Dispatch a fully-created Order to its kitchens.
     * Groups order products and addons by kitchen_id and creates KitchenOrder records.
     */
    public function dispatchFromOrder(Order $order): void
    {
        $order->loadMissing([
            'orderProducts.product',
            'orderProducts.variations.options',
            'orderProducts.addons.addon',
        ]);

        /** @var array<int, array{products: list<array>, addons: list<array>}> $kitchenGroups */
        $kitchenGroups = [];

        foreach ($order->orderProducts as $orderProduct) {
            $productKitchenId = $orderProduct->product?->kitchen_id;

            if ($productKitchenId) {
                if (! isset($kitchenGroups[$productKitchenId])) {
                    $kitchenGroups[$productKitchenId] = ['products' => [], 'addons' => []];
                }

                // Collect option IDs from all variations for this product
                $optionIds = [];
                foreach ($orderProduct->variations as $variation) {
                    foreach ($variation->options as $option) {
                        $optionIds[] = $option->option_id;
                    }
                }

                $kitchenGroups[$productKitchenId]['products'][] = [
                    'product_id' => $orderProduct->product_id,
                    'quantity' => $orderProduct->quantity,
                    'notes' => $orderProduct->note,
                    'option_ids' => $optionIds,
                ];
            }

            // Addons may belong to a different kitchen than the product
            foreach ($orderProduct->addons as $orderAddon) {
                $addonKitchenId = $orderAddon->addon?->kitchen_id;

                if ($addonKitchenId) {
                    if (! isset($kitchenGroups[$addonKitchenId])) {
                        $kitchenGroups[$addonKitchenId] = ['products' => [], 'addons' => []];
                    }

                    $kitchenGroups[$addonKitchenId]['addons'][] = [
                        'product_id' => $orderProduct->product_id,
                        'addon_id' => $orderAddon->addon_id,
                        'quantity' => $orderProduct->quantity,
                    ];
                }
            }
        }

        DB::transaction(function () use ($order, $kitchenGroups): void {
            foreach ($kitchenGroups as $kitchenId => $group) {
                $kitchenOrder = KitchenOrder::create([
                    'kitchen_id' => $kitchenId,
                    'order_id' => $order->id,
                    'order_cart_id' => null,
                ]);

                foreach ($group['products'] as $productData) {
                    $item = KitchenOrderItem::create([
                        'kitchen_order_id' => $kitchenOrder->id,
                        'product_id' => $productData['product_id'],
                        'addon_id' => null,
                        'quantity' => $productData['quantity'],
                        'notes' => $productData['notes'],
                    ]);

                    if (! empty($productData['option_ids'])) {
                        KitchenOrderDetail::create([
                            'kitchen_order_item_id' => $item->id,
                            'option_ids' => $productData['option_ids'],
                        ]);
                    }
                }

                foreach ($group['addons'] as $addonData) {
                    KitchenOrderItem::create([
                        'kitchen_order_id' => $kitchenOrder->id,
                        'product_id' => $addonData['product_id'],
                        'addon_id' => $addonData['addon_id'],
                        'quantity' => $addonData['quantity'],
                        'notes' => null,
                    ]);
                }
            }
        });
    }

    /**
     * Dispatch a single OrderCart item (dinein) to its kitchen.
     * Each cart item is dispatched independently with order_cart_id reference.
     */
    public function dispatchFromCart(OrderCart $cart): void
    {
        $cart->loadMissing([
            'product',
            'variationCarts',
            'addonCarts.addon',
        ]);

        $productKitchenId = $cart->product?->kitchen_id;

        DB::transaction(function () use ($cart, $productKitchenId): void {
            if ($productKitchenId) {
                $kitchenOrder = KitchenOrder::create([
                    'kitchen_id' => $productKitchenId,
                    'order_id' => null,
                    'order_cart_id' => $cart->id,
                ]);

                // Collect option IDs from variation carts (JSON stored in order_variation_carts)
                $optionIds = [];
                foreach ($cart->variationCarts as $varCart) {
                    $ids = is_array($varCart->option_ids) ? $varCart->option_ids : [];
                    foreach ($ids as $id) {
                        $optionIds[] = $id;
                    }
                }

                $item = KitchenOrderItem::create([
                    'kitchen_order_id' => $kitchenOrder->id,
                    'product_id' => $cart->product_id,
                    'addon_id' => null,
                    'quantity' => $cart->quantity,
                    'notes' => $cart->notes,
                ]);

                if (! empty($optionIds)) {
                    KitchenOrderDetail::create([
                        'kitchen_order_item_id' => $item->id,
                        'option_ids' => $optionIds,
                    ]);
                }
            }

            // Addons may belong to different kitchens
            foreach ($cart->addonCarts as $addonCart) {
                $addonKitchenId = $addonCart->addon?->kitchen_id;

                if (! $addonKitchenId) {
                    continue;
                }

                $kitchenOrder = KitchenOrder::where('kitchen_id', $addonKitchenId)
                    ->where('order_cart_id', $cart->id)
                    ->first();

                if (! $kitchenOrder) {
                    $kitchenOrder = KitchenOrder::create([
                        'kitchen_id' => $addonKitchenId,
                        'order_id' => null,
                        'order_cart_id' => $cart->id,
                    ]);
                }

                KitchenOrderItem::create([
                    'kitchen_order_id' => $kitchenOrder->id,
                    'product_id' => $cart->product_id,
                    'addon_id' => $addonCart->addon_id,
                    'quantity' => $cart->quantity,
                    'notes' => null,
                ]);
            }
        });
    }
}
