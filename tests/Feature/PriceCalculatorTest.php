<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use App\Services\PriceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PriceCalculatorService::class);

    $this->tax14 = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => '14% VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $this->discount10 = Discount::create([
        'name' => ['ar' => 'خصم 10%', 'en' => '10% Off'],
        'type' => 'percentage',
        'amount' => 10.00,
        'status' => true,
    ]);

    $this->discountFixed20 = Discount::create([
        'name' => ['ar' => 'خصم 20 جنيه', 'en' => '20 EGP Off'],
        'type' => 'value',
        'amount' => 20.00,
        'status' => true,
    ]);
});

test('calculateProduct calculates discount and tax independently on the original price', function () {
    $product = Product::create([
        'name' => ['ar' => 'برجر كلاسيك', 'en' => 'Classic Burger'],
        'price' => 100.00,
        'image' => 'products/burger.jpg',
        'discount_id' => $this->discount10->id,
        'tax_id' => $this->tax14->id,
    ]);

    // Original price: 100
    // Discount 10% on original price: 10.00
    // Tax 14% on original price (before discount): 14.00
    // Dine-in tax 10% on original price: 10.00
    // Final price = (100 - 10) + 14 + 10 = 114.00
    $result = $this->service->calculateProduct($product, 10.0);

    expect($result['price'])->toBe(100.0)
        ->and($result['discount_val'])->toBe(10.0)
        ->and($result['tax_val'])->toBe(14.0)
        ->and($result['dinein_tax_val'])->toBe(10.0)
        ->and($result['final_price'])->toBe(114.0);
});

test('calculateProduct with fixed discount calculates tax on original price before discount', function () {
    $product = Product::create([
        'name' => ['ar' => 'برجر كلاسيك', 'en' => 'Classic Burger'],
        'price' => 100.00,
        'image' => 'products/burger.jpg',
        'discount_id' => $this->discountFixed20->id,
        'tax_id' => $this->tax14->id,
    ]);

    // Original price: 100
    // Fixed discount: 20.00
    // Tax 14% on original price (100): 14.00
    // Final price = (100 - 20) + 14 = 94.00
    $result = $this->service->calculateProduct($product, 0.0);

    expect($result['price'])->toBe(100.0)
        ->and($result['discount_val'])->toBe(20.0)
        ->and($result['tax_val'])->toBe(14.0)
        ->and($result['final_price'])->toBe(94.0);
});

test('calculateOption calculates option tax on original option price before discount', function () {
    $product = Product::create([
        'name' => ['ar' => 'بيتزا', 'en' => 'Pizza'],
        'price' => 80.00,
        'image' => 'products/pizza.jpg',
        'discount_id' => $this->discount10->id,
        'tax_id' => $this->tax14->id,
    ]);

    $variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $product->id,
        'status' => true,
    ]);

    $option = Option::create([
        'name' => ['ar' => 'كبير', 'en' => 'Large'],
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'price' => 50.00,
        'status' => true,
    ]);

    // Option original price: 50.00
    // Discount 10% on option price: 5.00
    // Tax 14% on option original price (50.00): 7.00
    // Final price = (50 - 5) + 7 = 52.00
    $result = $this->service->calculateOption($option, $this->discount10, $this->tax14);

    expect($result['price'])->toBe(50.0)
        ->and($result['discount_val'])->toBe(5.0)
        ->and($result['tax_val'])->toBe(7.0)
        ->and($result['final_price'])->toBe(52.0);
});

test('calculateAddon calculates addon tax on original addon price before discount', function () {
    $addon = Addon::create([
        'name' => ['ar' => 'جبنة زيادة', 'en' => 'Extra Cheese'],
        'price' => 30.00,
        'image' => 'addons/cheese.jpg',
        'discount_id' => $this->discount10->id,
        'tax_id' => $this->tax14->id,
    ]);

    // Addon price: 30.00
    // Discount 10% on original price: 3.00
    // Tax 14% on original price (30.00): 4.20
    // Final price = (30 - 3) + 4.20 = 31.20
    $result = $this->service->calculateAddon($addon);

    expect($result['price'])->toBe(30.0)
        ->and($result['discount_val'])->toBe(3.0)
        ->and($result['tax_val'])->toBe(4.2)
        ->and($result['final_price'])->toBe(31.2);
});

test('calculateCartItem and grand totals correctly aggregate items with tax calculated on original price', function () {
    $branch = Branch::create(['name' => ['ar' => 'فرع', 'en' => 'Branch'], 'status' => true]);
    $hall = Hall::create(['name' => ['ar' => 'صالة', 'en' => 'Hall'], 'branch_id' => $branch->id, 'status' => true]);
    $table = HallTable::create(['name' => 'T1', 'hall_id' => $hall->id, 'branch_id' => $branch->id, 'status' => true]);

    $product = Product::create([
        'name' => ['ar' => 'وجبة دجاج', 'en' => 'Chicken Meal'],
        'price' => 100.00,
        'image' => 'products/chicken.jpg',
        'discount_id' => $this->discount10->id,
        'tax_id' => $this->tax14->id,
    ]);

    $cart = OrderCart::create([
        'module' => 'takeaway',
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $cart->load([
        'product.discount',
        'product.tax',
        'variationCarts.variation',
        'addonCarts.addon.discount',
        'addonCarts.addon.tax',
    ]);

    // Unit Price = 100.00
    // Unit Discount = 10.00 (10% of 100)
    // Unit Tax = 14.00 (14% of 100)
    // Unit Final = 104.00
    // Qty = 2
    // Total Price = 200.00
    // Total Discount = 20.00
    // Total Tax = 28.00
    // Total Final = 208.00
    $calculated = $this->service->calculateCartItem($cart);

    expect($calculated['total_price'])->toBe(200.0)
        ->and($calculated['total_discount'])->toBe(20.0)
        ->and($calculated['total_tax'])->toBe(28.0)
        ->and($calculated['total_final_price'])->toBe(208.0);

    $grand = $this->service->calculateGrandTotals([$calculated]);

    expect($grand['grand_total_price'])->toBe(200.0)
        ->and($grand['grand_total_discount'])->toBe(20.0)
        ->and($grand['grand_total_tax'])->toBe(28.0)
        ->and($grand['grand_final_price'])->toBe(208.0);
});
