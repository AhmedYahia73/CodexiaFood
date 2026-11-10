<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\BusinessSetup;
use App\Models\Discount;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;

class PriceCalculatorService
{
    protected ?float $cachedDineinTax = null;

    /**
     * Get dine-in tax rate percentage from business setup (cached in memory per request/service instance).
     */
    public function getDineinTax(): float
    {
        if ($this->cachedDineinTax === null) {
            $this->cachedDineinTax = (float) (BusinessSetup::first()?->dinein_tax ?? 0.0);
        }

        return $this->cachedDineinTax;
    }

    /**
     * Set or clear the cached dine-in tax rate.
     */
    public function setCachedDineinTax(?float $dineinTax): void
    {
        $this->cachedDineinTax = $dineinTax;
    }

    /**
     * Calculate price, discount_val, tax_val, dinein_tax_val, and final_price for a product.
     *
     * @return array{price: float, discount_val: float, tax_val: float, dinein_tax_val: float, final_price: float}
     */
    public function calculateProduct(Product $product, float $dineinTaxPercent = 0.0, ?Discount $customDiscount = null, bool $withoutTax = false): array
    {
        $price = (float) $product->price;

        $discountVal = 0.00;
        $discount = $customDiscount ?? $product->discount;
        if ($discount && ($discount->status ?? true)) {
            if ($discount->type === 'percentage') {
                $discountVal = round($price * ($discount->amount / 100), 2);
            } else {
                $discountVal = round(min($price, (float) $discount->amount), 2);
            }
        }

        $priceAfterDiscount = max(0.00, $price - $discountVal);

        $taxVal = 0.00;
        if (! $withoutTax) {
            $tax = $product->tax;
            if ($tax && ($tax->status ?? true)) {
                if ($tax->type === 'percentage') {
                    $taxVal = round($price * ($tax->amount / 100), 2);
                } else {
                    $taxVal = round((float) $tax->amount, 2);
                }
            }
        }

        $dineinTaxVal = 0.00;
        if (! $withoutTax && $dineinTaxPercent > 0) {
            $dineinTaxVal = round($price * ($dineinTaxPercent / 100), 2);
        }

        $finalPrice = round($priceAfterDiscount + $taxVal + $dineinTaxVal, 2);

        return [
            'price' => $price,
            'discount_val' => $discountVal,
            'tax_val' => $taxVal,
            'dinein_tax_val' => $dineinTaxVal,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Calculate price, discount_val, tax_val, and final_price for a variation option.
     * Applies parent product discount ONLY if product discount type is 'percentage'.
     *
     * @return array{price: float, discount_val: float, tax_val: float, final_price: float}
     */
    public function calculateOption(Option $option, ?Discount $productDiscount = null, ?Tax $productTax = null, bool $withoutTax = false): array
    {
        $price = (float) $option->price;

        $discountVal = 0.00;
        if ($productDiscount && ($productDiscount->status ?? true) && $productDiscount->type === 'percentage') {
            $discountVal = round($price * ($productDiscount->amount / 100), 2);
        }

        $priceAfterDiscount = max(0.00, $price - $discountVal);

        $taxVal = 0.00;
        if (! $withoutTax && $productTax && ($productTax->status ?? true) && $productTax->type === 'percentage') {
            $taxVal = round($price * ($productTax->amount / 100), 2);
        }

        $finalPrice = round($priceAfterDiscount + $taxVal, 2);

        return [
            'price' => $price,
            'discount_val' => $discountVal,
            'tax_val' => $taxVal,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Calculate price, discount_val, tax_val, and final_price for an addon.
     *
     * @return array{price: float, discount_val: float, tax_val: float, final_price: float}
     */
    public function calculateAddon(Addon $addon, bool $withoutTax = false): array
    {
        $price = (float) $addon->price;

        $discountVal = 0.00;
        $discount = $addon->discount;
        if ($discount && ($discount->status ?? true)) {
            if ($discount->type === 'percentage') {
                $discountVal = round($price * ($discount->amount / 100), 2);
            } else {
                $discountVal = round(min($price, (float) $discount->amount), 2);
            }
        }

        $priceAfterDiscount = max(0.00, $price - $discountVal);

        $taxVal = 0.00;
        if (! $withoutTax) {
            $tax = $addon->tax;
            if ($tax && ($tax->status ?? true)) {
                if ($tax->type === 'percentage') {
                    $taxVal = round($price * ($tax->amount / 100), 2);
                } else {
                    $taxVal = round((float) $tax->amount, 2);
                }
            }
        }

        $finalPrice = round($priceAfterDiscount + $taxVal, 2);

        return [
            'price' => $price,
            'discount_val' => $discountVal,
            'tax_val' => $taxVal,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Calculate all prices, item totals, and formatted tree for an OrderCart item.
     *
     * @return array<string, mixed>
     */
    public function calculateCartItem(OrderCart $cart, string $locale = 'ar', ?float $dineinTax = null, bool $withoutTax = false): array
    {
        $product = $cart->product;
        $qty = max(1, (int) $cart->quantity);
        $isGift = (bool) ($cart->is_gift ?? false);
        $effectiveDiscount = $cart->discount ?? $product->discount;

        $dineinTaxRate = (! $withoutTax && $cart->module === 'dinein')
            ? ($dineinTax ?? ($cart->dinein_tax > 0 ? (float) $cart->dinein_tax : $this->getDineinTax()))
            : 0.0;

        if ($isGift) {
            $giftPrice = (float) ($cart->price ?? 1.00);
            $productPricing = [
                'price' => $giftPrice,
                'discount_val' => 0.00,
                'tax_val' => 0.00,
                'dinein_tax_val' => 0.00,
                'final_price' => $giftPrice,
            ];
        } else {
            $productPricing = $this->calculateProduct($product, $dineinTaxRate, $effectiveDiscount, $withoutTax);
        }

        $sumOptionsPrice = 0.00;
        $sumOptionsDiscount = 0.00;
        $sumOptionsTax = 0.00;
        $sumOptionsFinal = 0.00;

        $variationsData = [];
        foreach ($cart->variationCarts as $variationCart) {
            $variation = $variationCart->variation;
            $options = $variationCart->getOptions();

            $optionsData = [];
            foreach ($options as $option) {
                if ($isGift) {
                    $optPricing = [
                        'price' => 0.00,
                        'discount_val' => 0.00,
                        'tax_val' => 0.00,
                        'final_price' => 0.00,
                    ];
                } else {
                    $optPricing = $this->calculateOption($option, $effectiveDiscount, $product->tax, $withoutTax);
                }

                $sumOptionsPrice += $optPricing['price'];
                $sumOptionsDiscount += $optPricing['discount_val'];
                $sumOptionsTax += $optPricing['tax_val'];
                $sumOptionsFinal += $optPricing['final_price'];

                $optName = is_array($option->name)
                    ? ($option->name[$locale] ?? $option->name['ar'] ?? $option->name['en'] ?? null)
                    : $option->name;

                $optionsData[] = [
                    'id' => $option->id,
                    'name' => $optName,
                    'price' => $optPricing['price'],
                    'discount_val' => $optPricing['discount_val'],
                    'tax_val' => $optPricing['tax_val'],
                    'final_price' => $optPricing['final_price'],
                ];
            }

            $varName = is_array($variation?->name)
                ? ($variation->name[$locale] ?? $variation->name['ar'] ?? $variation->name['en'] ?? null)
                : $variation?->name;

            $variationsData[] = [
                'id' => $variationCart->id,
                'variation_id' => $variationCart->variation_id,
                'name' => $varName,
                'options' => $optionsData,
            ];
        }

        $sumAddonsPrice = 0.00;
        $sumAddonsDiscount = 0.00;
        $sumAddonsTax = 0.00;
        $sumAddonsFinal = 0.00;

        $addonsData = [];
        foreach ($cart->addonCarts as $addonCart) {
            $addon = $addonCart->addon;
            if (! $addon) {
                continue;
            }

            if ($isGift) {
                $addPricing = [
                    'price' => 0.00,
                    'discount_val' => 0.00,
                    'tax_val' => 0.00,
                    'final_price' => 0.00,
                ];
            } else {
                $addPricing = $this->calculateAddon($addon, $withoutTax);
            }

            $sumAddonsPrice += $addPricing['price'];
            $sumAddonsDiscount += $addPricing['discount_val'];
            $sumAddonsTax += $addPricing['tax_val'];
            $sumAddonsFinal += $addPricing['final_price'];

            $addName = is_array($addon->name)
                ? ($addon->name[$locale] ?? $addon->name['ar'] ?? $addon->name['en'] ?? null)
                : $addon->name;

            $addonsData[] = [
                'id' => $addonCart->id,
                'addon_id' => $addonCart->addon_id,
                'name' => $addName,
                'price' => $addPricing['price'],
                'discount_val' => $addPricing['discount_val'],
                'tax_val' => $addPricing['tax_val'],
                'final_price' => $addPricing['final_price'],
            ];
        }

        $unitPrice = $productPricing['price'] + $sumOptionsPrice + $sumAddonsPrice;
        $unitDiscount = $productPricing['discount_val'] + $sumOptionsDiscount + $sumAddonsDiscount;
        $unitTax = $productPricing['tax_val'] + $sumOptionsTax + $sumAddonsTax;
        $unitDineinTax = $productPricing['dinein_tax_val'] ?? 0.00;
        $unitFinal = $productPricing['final_price'] + $sumOptionsFinal + $sumAddonsFinal;

        $totalPrice = round($unitPrice * $qty, 2);
        $totalDiscount = round($unitDiscount * $qty, 2);
        $totalTax = round($unitTax * $qty, 2);
        $totalDineinTax = round($unitDineinTax * $qty, 2);
        $totalFinalPrice = round($unitFinal * $qty, 2);

        $prodName = is_array($product->name)
            ? ($product->name[$locale] ?? $product->name['ar'] ?? $product->name['en'] ?? null)
            : $product->name;

        $prodDesc = is_array($product->description)
            ? ($product->description[$locale] ?? $product->description['ar'] ?? $product->description['en'] ?? null)
            : $product->description;

        return [
            'id' => $cart->id,
            'module' => $cart->module,
            'hall_table_id' => $cart->hall_table_id,
            'table_id' => $cart->hall_table_id,
            'captain_order_id' => $cart->captain_order_id,
            'status' => $cart->status,
            'quantity' => $qty,
            'notes' => $cart->notes,
            'is_gift' => $isGift,
            'parent_id' => $cart->parent_id,
            'cashier_id' => $cart->cashier_id,
            'cashier_man_id' => $cart->cashier_man_id,
            'branch_id' => $cart->branch_id,
            'discount_id' => $cart->discount_id,
            'without_tax' => $withoutTax,
            'dinein_tax' => $withoutTax ? 0.0 : (float) ($cart->dinein_tax ?? $dineinTaxRate),
            'discount' => $effectiveDiscount ? [
                'id' => $effectiveDiscount->id,
                'name' => is_array($effectiveDiscount->name)
                    ? ($effectiveDiscount->name[$locale] ?? $effectiveDiscount->name['ar'] ?? $effectiveDiscount->name['en'] ?? '')
                    : $effectiveDiscount->name,
                'type' => $effectiveDiscount->type,
                'amount' => (float) $effectiveDiscount->amount,
            ] : null,
            'product' => [
                'id' => $product->id,
                'name' => $prodName,
                'description' => $prodDesc,
                'image' => $product->image,
                'price' => $productPricing['price'],
                'discount_val' => $productPricing['discount_val'],
                'tax_val' => $productPricing['tax_val'],
                'dinein_tax_val' => $productPricing['dinein_tax_val'] ?? 0.00,
                'final_price' => $productPricing['final_price'],
            ],
            'variations' => $variationsData,
            'addons' => $addonsData,
            'total_price' => $totalPrice,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'total_dinein_tax' => $totalDineinTax,
            'total_final_price' => $totalFinalPrice,
        ];
    }

    /**
     * Calculate grand totals from an array of calculated cart items.
     *
     * @param  array<int, array<string, mixed>>  $calculatedItems
     * @return array{grand_total_price: float, grand_total_discount: float, grand_total_tax: float, grand_total_dinein_tax: float, grand_final_price: float, without_tax: bool}
     */
    public function calculateGrandTotals(array $calculatedItems, bool $withoutTax = false): array
    {
        $grandTotalPrice = 0.00;
        $grandTotalDiscount = 0.00;
        $grandTotalTax = 0.00;
        $grandTotalDineinTax = 0.00;
        $grandFinalPrice = 0.00;

        foreach ($calculatedItems as $item) {
            $grandTotalPrice += (float) ($item['total_price'] ?? 0);
            $grandTotalDiscount += (float) ($item['total_discount'] ?? 0);
            $grandTotalTax += (float) ($item['total_tax'] ?? 0);
            $grandTotalDineinTax += (float) ($item['total_dinein_tax'] ?? 0);
            $grandFinalPrice += (float) ($item['total_final_price'] ?? 0);
        }

        return [
            'grand_total_price' => round($grandTotalPrice, 2),
            'grand_total_discount' => round($grandTotalDiscount, 2),
            'grand_total_tax' => $withoutTax ? 0.00 : round($grandTotalTax, 2),
            'grand_total_dinein_tax' => $withoutTax ? 0.00 : round($grandTotalDineinTax, 2),
            'grand_final_price' => round($grandFinalPrice, 2),
            'without_tax' => $withoutTax,
        ];
    }
}
