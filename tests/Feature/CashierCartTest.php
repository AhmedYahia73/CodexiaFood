<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Discount;
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
        ->getJson('/api/cashier/cart?module=dinein&lang=ar')
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

    // Fetch dinein only
    $dineinRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/cart?module=dinein')
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
