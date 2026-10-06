<?php

namespace App\Observers;

use App\Models\OrderCart;
use App\Services\KitchenDispatchService;

class OrderCartObserver
{
    /**
     * Delete all related KitchenOrders when the order cart reaches a terminal status.
     * Re-dispatch if cart status transitions back to preparing for dinein.
     */
    public function updated(OrderCart $orderCart): void
    {
        if (! $orderCart->wasChanged('status')) {
            return;
        }

        if (in_array($orderCart->status, OrderCart::$kitchenCleanupStatuses, true)) {
            $orderCart->kitchenOrders()->delete();
        } elseif ($orderCart->status === 'preparing' && $orderCart->module === 'dinein') {
            app(KitchenDispatchService::class)->dispatchFromCart($orderCart);
        }
    }

    /**
     * Delete all related KitchenOrders when the cart item is deleted.
     */
    public function deleted(OrderCart $orderCart): void
    {
        $orderCart->kitchenOrders()->delete();
    }
}
