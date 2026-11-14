<?php

namespace App\trait;

use App\Models\OrderCart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait CashierCartScope
{
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

    private function getTaxScopeKey(string $module, ?int $tableId, ?int $cashierId): string
    {
        if ($module === 'dinein') {
            return "cashier_cart_without_tax:dinein:table_{$tableId}";
        }

        return "cashier_cart_without_tax:{$module}:cashier_{$cashierId}";
    }

    private function resolveWithoutTax(Request $request, string $module, ?int $tableId, ?int $cashierId): bool
    {
        $hasTaxParam = $request->has('without_tax')
            || $request->has('no_tax')
            || $request->has('cancel_tax')
            || $request->has('exclude_tax');

        $scopeKey = $this->getTaxScopeKey($module, $tableId, $cashierId);

        if ($hasTaxParam) {
            $withoutTax = $request->boolean('without_tax')
                || $request->boolean('no_tax')
                || $request->boolean('cancel_tax')
                || $request->boolean('exclude_tax');

            Cache::put($scopeKey, $withoutTax, now()->addHours(12));

            return $withoutTax;
        }

        return (bool) Cache::get($scopeKey, false);
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
            $query->where('hall_table_id', $tableId);
        }, function ($query) use ($cashierId) {
            $query->where(function ($q) use ($cashierId) {
                $q->where('cashier_id', $cashierId)
                    ->orWhereNull('cashier_id');
            });
        });
    }
}
