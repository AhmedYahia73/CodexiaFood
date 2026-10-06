<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\KitchenDispatchService;

class OrderObserver
{
    /**
     * Delete all related KitchenOrders when the order reaches a terminal/delivery status.
     * Re-dispatch if order status transitions back to preparing.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if (in_array($order->status, Order::$kitchenCleanupStatuses, true)) {
            $order->kitchenOrders()->delete();
        } elseif ($order->status === 'preparing' && $order->module !== 'dinein') {
            app(KitchenDispatchService::class)->dispatchFromOrder($order);
        }
    }

    /**
     * Delete all related KitchenOrders when the order is deleted.
     */
    public function deleted(Order $order): void
    {
        $order->kitchenOrders()->delete();
    }
}
