<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Discount;
use App\Models\Option;
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
}
