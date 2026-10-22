<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\BusinessSetup;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Discount;
use App\Models\FinancialAccount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
        'status' => true,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Tarek Cashier',
        'password' => 'password123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->hallTable = HallTable::create([
        'name' => 'Table #1',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    // 20% discount
    $this->discount = Discount::create([
        'name' => ['ar' => 'خصم 20%', 'en' => '20% Discount'],
        'type' => 'percentage',
        'amount' => 20.00,
        'status' => true,
    ]);

    // 14% tax
    $this->tax = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => '14% VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'برجر دجاج سوبر', 'en' => 'Super Chicken Burger'],
        'description' => ['ar' => 'وصف شهي', 'en' => 'Tasty description'],
        'price' => 100.00,
        'image' => 'products/chicken.jpg',
        'discount_id' => $this->discount->id,
        'tax_id' => $this->tax->id,
    ]);

    $this->variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $this->product->id,
        'status' => true,
        'required' => true,
    ]);

    $this->option = Option::create([
        'name' => ['ar' => 'كبير جدا', 'en' => 'Extra Large'],
        'product_id' => $this->product->id,
        'variation_id' => $this->variation->id,
        'price' => 20.00,
        'status' => true,
    ]);

    $this->addon = Addon::create([
        'name' => ['ar' => 'مايونيز مدخن', 'en' => 'Smoked Mayo'],
        'price' => 10.00,
        'image' => 'addons/mayo.jpg',
    ]);
});

test('cashier can add item to cart with notes and options and addons', function () {
    $payload = [
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 2,
        'notes' => 'بدون مخلل',
        'variations' => [
            [
                'variation_id' => $this->variation->id,
                'option_ids' => [$this->option->id],
            ],
        ],
        'addons' => [
            [
                'addon_id' => $this->addon->id,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart?lang=ar', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.module', 'takeaway')
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.notes', 'بدون مخلل')
        ->assertJsonPath('data.cashier_id', $this->cashier->id);

    $this->assertDatabaseHas('order_carts', [
        'id' => $response->json('data.id'),
        'cashier_id' => $this->cashier->id,
        'notes' => 'بدون مخلل',
        'quantity' => 2,
    ]);
});

test('cashier cart index calculates item totals and grand totals correctly', function () {
    // Product: price = 100, discount 20% = 20, tax 14% on (80) = 11.20, final = 91.20
    // Option: price = 20, discount 20% = 4, tax 14% on (16) = 2.24, final = 18.24
    // Addon: price = 10, discount = 0, tax = 0, final = 10.00
    // Total Unit Price = 100 + 20 + 10 = 130
    // Total Unit Discount = 20 + 4 + 0 = 24
    // Total Unit Tax = 11.20 + 2.24 + 0 = 13.44
    // Total Unit Final = 91.20 + 18.24 + 10 = 119.44
    // Quantity = 2
    // Item Total Price = 130 * 2 = 260
    // Item Total Discount = 24 * 2 = 48
    // Item Total Tax = 13.44 * 2 = 26.88
    // Item Total Final = 119.44 * 2 = 238.88

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'حار جدا',
            'variations' => [
                [
                    'variation_id' => $this->variation->id,
                    'option_ids' => [$this->option->id],
                ],
            ],
            'addons' => [
                [
                    'addon_id' => $this->addon->id,
                ],
            ],
        ])
        ->assertStatus(201);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$this->hallTable->id.'&lang=ar')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data',
            'grand_totals' => [
                'grand_total_price',
                'grand_total_discount',
                'grand_total_tax',
                'grand_final_price',
            ],
        ]);

    $item = $response->json('data.0');
    expect((float) $item['total_price'])->toBe(260.0);
    expect((float) $item['total_discount'])->toBe(48.0);
    expect((float) $item['total_tax'])->toBe(26.88);
    expect((float) $item['total_final_price'])->toBe(238.88);

    $grand = $response->json('grand_totals');
    expect((float) $grand['grand_total_price'])->toBe(260.0);
    expect((float) $grand['grand_total_discount'])->toBe(48.0);
    expect((float) $grand['grand_total_tax'])->toBe(26.88);
    expect((float) $grand['grand_final_price'])->toBe(238.88);
});

test('cart is fetched by cashier_id so another cashier_man on the same desk sees the cart', function () {
    // Add item by first cashier_man
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(201);

    // Second cashier_man on the SAME cashier desk
    $secondCashierMan = CashierMan::create([
        'name' => 'Wael Cashier',
        'password' => 'password123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);
    $secondToken = JWTAuth::fromUser($secondCashierMan);

    $response = $this->withHeader('Authorization', 'Bearer '.$secondToken)
        ->getJson('/api/cashier/cart?module=takeaway')
        ->assertStatus(200);

    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.cashier_id'))->toBe($this->cashier->id);
});

test('cashier can update, delete, and clear cart items', function () {
    $storeRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(201);

    $cartId = $storeRes->json('data.id');

    // Update quantity
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/cashier/cart/'.$cartId, [
            'quantity' => 3,
            'notes' => 'ملاحظة محدثة',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.quantity', 3)
        ->assertJsonPath('data.notes', 'ملاحظة محدثة');

    // Delete single item
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/cashier/cart/'.$cartId)
        ->assertStatus(200);

    $this->assertDatabaseMissing('order_carts', ['id' => $cartId]);

    // Add another item and clear cart
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'delivery',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(201);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/cashier/cart/clear')
        ->assertStatus(200);

    $getRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=delivery')
        ->assertStatus(200);

    expect(count($getRes->json('data')))->toBe(0);
});

test('cashier cart index requires valid module and filters by specified module only', function () {
    // Missing module returns 422
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['module']);

    // Invalid module returns 422
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=table_order')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['module']);

    // Add 1 item for takeaway, 1 for dinein, 1 for delivery
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])->assertStatus(201);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ])->assertStatus(201);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'delivery',
            'product_id' => $this->product->id,
            'quantity' => 3,
        ])->assertStatus(201);

    // Fetch takeaway only
    $takeawayRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=takeaway')
        ->assertStatus(200);
    expect($takeawayRes->json('data'))->toHaveCount(1);
    expect($takeawayRes->json('data.0.module'))->toBe('takeaway');
    expect($takeawayRes->json('data.0.quantity'))->toBe(1);

    // Fetch dinein with table_id
    $dineinRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$this->hallTable->id)
        ->assertStatus(200);
    expect($dineinRes->json('data'))->toHaveCount(1);
    expect($dineinRes->json('data.0.module'))->toBe('dinein');
    expect($dineinRes->json('data.0.quantity'))->toBe(2);

    // Fetch delivery only
    $deliveryRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=delivery')
        ->assertStatus(200);
    expect($deliveryRes->json('data'))->toHaveCount(1);
    expect($deliveryRes->json('data.0.module'))->toBe('delivery');
    expect($deliveryRes->json('data.0.quantity'))->toBe(3);
});

test('cashier dinein cart requires table_id on store and isolates carts per table', function () {
    // Missing table_id on dinein store returns 422
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(422);

    // Create a second table
    $secondTable = HallTable::create([
        'name' => 'Table #2',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    // Add item for table 1
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ])
        ->assertStatus(201);

    // Add item for table 2
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'table_id' => $secondTable->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ])
        ->assertStatus(201);

    // Querying without table_id returns empty cart
    $emptyRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein')
        ->assertStatus(200);
    expect($emptyRes->json('data'))->toHaveCount(0);

    // Table 1 cart has 1 item with qty 2
    $t1Res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$this->hallTable->id)
        ->assertStatus(200);
    expect($t1Res->json('data'))->toHaveCount(1);
    expect($t1Res->json('data.0.quantity'))->toBe(2);

    // Table 2 cart has 1 item with qty 5
    $t2Res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$secondTable->id)
        ->assertStatus(200);
    expect($t2Res->json('data'))->toHaveCount(1);
    expect($t2Res->json('data.0.quantity'))->toBe(5);

    // Clearing table 1 does not affect table 2
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/cashier/cart/clear', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
        ])
        ->assertStatus(200);

    $t1After = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$this->hallTable->id)
        ->assertStatus(200);
    expect($t1After->json('data'))->toHaveCount(0);

    $t2After = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein&table_id='.$secondTable->id)
        ->assertStatus(200);
    expect($t2After->json('data'))->toHaveCount(1);
});

test('when has_gift is true, adding item adds original and gift item under each other at price_gift without tax or discount', function () {
    BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business_setup/logo.png',
        'description' => 'وصف المطعم',
        'has_gift' => true,
        'price_gift' => 1,
    ]);

    // Product with price 40, no discount, no tax for clear math
    $coffee = Product::create([
        'name' => ['ar' => 'قهوة تركي', 'en' => 'Turkish Coffee'],
        'description' => ['ar' => 'قهوة ممتازة', 'en' => 'Premium coffee'],
        'price' => 40.00,
        'image' => 'products/coffee.jpg',
    ]);

    $storeRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $coffee->id,
            'quantity' => 4,
            'notes' => 'سريعة',
        ])
        ->assertStatus(201);

    // Get cart items
    $cartRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=takeaway&lang=ar')
        ->assertStatus(200);

    $items = $cartRes->json('data');
    expect($items)->toHaveCount(2);

    // First item is regular: 4 coffee @ 40 = 160
    $regularItem = $items[0];
    expect($regularItem['is_gift'])->toBeFalse();
    expect((float) $regularItem['product']['price'])->toBe(40.0);
    expect((float) $regularItem['total_price'])->toBe(160.0);
    expect((float) $regularItem['total_discount'])->toBe(0.0);
    expect((float) $regularItem['total_tax'])->toBe(0.0);
    expect((float) $regularItem['total_final_price'])->toBe(160.0);

    // Second item (under it) is gift: 4 coffee @ 1 = 4, NO tax, NO discount!
    $giftItem = $items[1];
    expect($giftItem['is_gift'])->toBeTrue();
    expect((float) $giftItem['product']['price'])->toBe(1.0);
    expect((float) $giftItem['total_price'])->toBe(4.0);
    expect((float) $giftItem['total_discount'])->toBe(0.0);
    expect((float) $giftItem['total_tax'])->toBe(0.0);
    expect((float) $giftItem['total_final_price'])->toBe(4.0);

    // Grand totals: 160 + 4 = 164
    $grand = $cartRes->json('grand_totals');
    expect((float) $grand['grand_total_price'])->toBe(164.0);
    expect((float) $grand['grand_total_discount'])->toBe(0.0);
    expect((float) $grand['grand_total_tax'])->toBe(0.0);
    expect((float) $grand['grand_final_price'])->toBe(164.0);
});

test('updating quantity of original cart item also updates the gift cart item', function () {
    BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business_setup/logo.png',
        'description' => 'وصف المطعم',
        'has_gift' => true,
        'price_gift' => 1,
    ]);

    $coffee = Product::create([
        'name' => ['ar' => 'قهوة تركي', 'en' => 'Turkish Coffee'],
        'price' => 40.00,
        'image' => 'products/coffee.jpg',
    ]);

    $storeRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $coffee->id,
            'quantity' => 4,
        ])
        ->assertStatus(201);

    $cartId = $storeRes->json('data.id');

    // Update quantity to 6
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/cashier/cart/'.$cartId, [
            'quantity' => 6,
        ])
        ->assertStatus(200);

    $cartRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=takeaway')
        ->assertStatus(200);

    $items = $cartRes->json('data');
    expect($items)->toHaveCount(2);
    expect($items[0]['quantity'])->toBe(6);
    expect((float) $items[0]['total_final_price'])->toBe(240.0);
    expect($items[1]['quantity'])->toBe(6);
    expect((float) $items[1]['total_final_price'])->toBe(6.0);
});

test('deleting original cart item cascades and deletes the gift item', function () {
    BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business_setup/logo.png',
        'description' => 'وصف المطعم',
        'has_gift' => true,
        'price_gift' => 1,
    ]);

    $coffee = Product::create([
        'name' => ['ar' => 'قهوة تركي', 'en' => 'Turkish Coffee'],
        'price' => 40.00,
        'image' => 'products/coffee.jpg',
    ]);

    $storeRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $coffee->id,
            'quantity' => 4,
        ])
        ->assertStatus(201);

    $cartId = $storeRes->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/cashier/cart/'.$cartId)
        ->assertStatus(200);

    $cartRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=takeaway')
        ->assertStatus(200);

    expect($cartRes->json('data'))->toBeEmpty();
});

test('checking out with gift items creates correct order products with price_gift and is_gift', function () {
    BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business_setup/logo.png',
        'description' => 'وصف المطعم',
        'has_gift' => true,
        'price_gift' => 1,
    ]);

    $coffee = Product::create([
        'name' => ['ar' => 'قهوة تركي', 'en' => 'Turkish Coffee'],
        'price' => 40.00,
        'image' => 'products/coffee.jpg',
    ]);

    $account = FinancialAccount::create([
        'name' => ['ar' => 'خزينة رئيسية', 'en' => 'Main Cash'],
        'status' => true,
        'balance' => 0.00,
        'branch_id' => $this->branch->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $coffee->id,
            'quantity' => 4,
        ])
        ->assertStatus(201);

    $checkoutRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'takeaway',
            'financial_account_id' => $account->id,
        ])
        ->assertStatus(201);

    $orderData = $checkoutRes->json('data');
    expect((float) $orderData['final_price'])->toBe(164.0);
    expect($orderData['products'])->toHaveCount(2);

    $regProduct = $orderData['products'][0];
    expect((float) $regProduct['price'])->toBe(40.0);
    expect($regProduct['is_gift'])->toBeFalse();
    expect($regProduct['quantity'])->toBe(4);

    $giftProduct = $orderData['products'][1];
    expect((float) $giftProduct['price'])->toBe(1.0);
    expect($giftProduct['is_gift'])->toBeTrue();
    expect($giftProduct['quantity'])->toBe(4);
});
