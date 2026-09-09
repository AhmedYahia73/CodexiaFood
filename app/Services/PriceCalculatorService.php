<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Discount;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;

class PriceCalculatorService
{
    /**
     * Calculate price, discount_val, tax_val, and final_price for a product.
     *
     * @return array{price: float, discount_val: float, tax_val: float, final_price: float}
     */
    public function calculateProduct(Product $product): array
    {
        $price = (float) $product->price;

        $discountVal = 0.00;
        $discount = $product->discount;
        if ($discount && ($discount->status ?? true)) {
            if ($discount->type === 'percentage') {
                $discountVal = round($price * ($discount->amount / 100), 2);
            } else {
                $discountVal = round(min($price, (float) $discount->amount), 2);
            }
        }

        $priceAfterDiscount = max(0.00, $price - $discountVal);

        $taxVal = 0.00;
        $tax = $product->tax;
        if ($tax && ($tax->status ?? true)) {
            if ($tax->type === 'percentage') {
                $taxVal = round($priceAfterDiscount * ($tax->amount / 100), 2);
            } else {
                $taxVal = round((float) $tax->amount, 2);
            }
        }

        $finalPrice = round($price - $discountVal + $taxVal, 2);

        return [
            'price' => $price,
            'discount_val' => $discountVal,
            'tax_val' => $taxVal,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Calculate price, discount_val, tax_val, and final_price for a variation option.
     * Applies parent product discount ONLY if product discount type is 'percentage'.
     *
     * @return array{price: float, discount_val: float, tax_val: float, final_price: float}
     */
    public function calculateOption(Option $option, ?Discount $productDiscount = null, ?Tax $productTax = null): array
    {
        $price = (float) $option->price;

        $discountVal = 0.00;
        if ($productDiscount && ($productDiscount->status ?? true) && $productDiscount->type === 'percentage') {
            $discountVal = round($price * ($productDiscount->amount / 100), 2);
        }

        $priceAfterDiscount = max(0.00, $price - $discountVal);

        $taxVal = 0.00;
        if ($productTax && ($productTax->status ?? true) && $productTax->type === 'percentage') {
            $taxVal = round($priceAfterDiscount * ($productTax->amount / 100), 2);
        }

        $finalPrice = round($price - $discountVal + $taxVal, 2);

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
    public function calculateAddon(Addon $addon): array
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
        $tax = $addon->tax;
        if ($tax && ($tax->status ?? true)) {
            if ($tax->type === 'percentage') {
                $taxVal = round($priceAfterDiscount * ($tax->amount / 100), 2);
            } else {
                $taxVal = round((float) $tax->amount, 2);
            }
        }

        $finalPrice = round($price - $discountVal + $taxVal, 2);

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
    public function calculateCartItem(OrderCart $cart, string $locale = 'ar'): array
    {
        $product = $cart->product;
        $qty = max(1, (int) $cart->quantity);

        $productPricing = $this->calculateProduct($product);

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
                $optPricing = $this->calculateOption($option, $product->discount, $product->tax);
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

            $addPricing = $this->calculateAddon($addon);
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
        $unitFinal = $productPricing['final_price'] + $sumOptionsFinal + $sumAddonsFinal;

        $totalPrice = round($unitPrice * $qty, 2);
        $totalDiscount = round($unitDiscount * $qty, 2);
        $totalTax = round($unitTax * $qty, 2);
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
            'quantity' => $qty,
            'notes' => $cart->notes,
            'cashier_id' => $cart->cashier_id,
            'cashier_man_id' => $cart->cashier_man_id,
            'branch_id' => $cart->branch_id,
            'product' => [
                'id' => $product->id,
                'name' => $prodName,
                'description' => $prodDesc,
                'image' => $product->image,
                'price' => $productPricing['price'],
                'discount_val' => $productPricing['discount_val'],
                'tax_val' => $productPricing['tax_val'],
                'final_price' => $productPricing['final_price'],
            ],
            'variations' => $variationsData,
            'addons' => $addonsData,
            'total_price' => $totalPrice,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'total_final_price' => $totalFinalPrice,
        ];
    }

    /**
     * Calculate grand totals from an array of calculated cart items.
     *
     * @param  array<int, array<string, mixed>>  $calculatedItems
     * @return array{grand_total_price: float, grand_total_discount: float, grand_total_tax: float, grand_final_price: float}
     */
    public function calculateGrandTotals(array $calculatedItems): array
    {
        $grandTotalPrice = 0.00;
        $grandTotalDiscount = 0.00;
        $grandTotalTax = 0.00;
        $grandFinalPrice = 0.00;

        foreach ($calculatedItems as $item) {
            $grandTotalPrice += (float) ($item['total_price'] ?? 0);
            $grandTotalDiscount += (float) ($item['total_discount'] ?? 0);
            $grandTotalTax += (float) ($item['total_tax'] ?? 0);
            $grandFinalPrice += (float) ($item['total_final_price'] ?? 0);
        }

        return [
            'grand_total_price' => round($grandTotalPrice, 2),
            'grand_total_discount' => round($grandTotalDiscount, 2),
            'grand_total_tax' => round($grandTotalTax, 2),
            'grand_final_price' => round($grandFinalPrice, 2),
        ];
    }
}
