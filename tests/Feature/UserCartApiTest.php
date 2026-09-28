<?php

use App\Models\Addon;
use App\Models\Discount;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
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

    $this->uuId1 = 'client-uuid-1111';
    $this->uuId2 = 'client-uuid-2222';
});

test('public user can add item to cart with uu_id without auth and without cashier/branch/table data', function () {
    $payload = [
        'uu_id' => $this->uuId1,
        'module' => 'delivery',
        'product_id' => $this->product->id,
        'quantity' => 2,
        'notes' => 'بدون طماطم',
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

    $response = $this->postJson('/api/user/cart', $payload)
        ->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح',
            'uu_id' => $this->uuId1,
        ]);

    $cartId = $response->json('data.id');
    $cart = OrderCart::find($cartId);

    expect($cart)->not->toBeNull();
    expect($cart->uu_id)->toBe($this->uuId1);
    expect($cart->cashier_id)->toBeNull();
    expect($cart->cashier_man_id)->toBeNull();
    expect($cart->branch_id)->toBeNull();
    expect($cart->hall_table_id)->toBeNull();
    expect($cart->quantity)->toBe(2);
    expect($cart->notes)->toBe('بدون طماطم');
});

test('user cart requires uu_id in index', function () {
    $this->getJson('/api/user/cart')
        ->assertStatus(400)
        ->assertJson([
            'status' => false,
            'message' => 'يرجى تحديد معرف المستخدم (uu_id)',
        ]);
});

test('user cart index returns only items matching the given uu_id and calculates grand totals', function () {
    // Add item for user 1
    $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId1,
        'module' => 'delivery',
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    // Add item for user 2
    $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId2,
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 3,
    ])->assertStatus(201);

    // Fetch user 1 cart
    $resUser1 = $this->getJson('/api/user/cart?uu_id='.$this->uuId1)
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'uu_id',
            'data',
            'grand_totals' => ['grand_total_price', 'grand_total_discount', 'grand_total_tax', 'grand_final_price'],
        ]);

    expect($resUser1->json('data'))->toHaveCount(1);
    expect($resUser1->json('data.0.quantity'))->toBe(1);

    // Fetch user 2 cart
    $resUser2 = $this->getJson('/api/user/cart?uu_id='.$this->uuId2)
        ->assertStatus(200);

    expect($resUser2->json('data'))->toHaveCount(1);
    expect($resUser2->json('data.0.quantity'))->toBe(3);
});

test('user can show, update, delete, and clear cart items with uu_id', function () {
    // 1. Add item
    $storeRes = $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId1,
        'module' => 'delivery',
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    $cartId = $storeRes->json('data.id');

    // 2. Show item
    $this->getJson('/api/user/cart/'.$cartId.'?uu_id='.$this->uuId1)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $cartId);

    // 3. Update item
    $this->putJson('/api/user/cart/'.$cartId, [
        'uu_id' => $this->uuId1,
        'quantity' => 5,
        'notes' => 'تعديل الملاحظات',
    ])->assertStatus(200)
        ->assertJsonPath('data.quantity', 5)
        ->assertJsonPath('data.notes', 'تعديل الملاحظات');

    // Cross-user protection: user 2 cannot update user 1 item
    $this->putJson('/api/user/cart/'.$cartId, [
        'uu_id' => $this->uuId2,
        'quantity' => 10,
    ])->assertStatus(404);

    // 4. Delete item
    $this->deleteJson('/api/user/cart/'.$cartId.'?uu_id='.$this->uuId1)
        ->assertStatus(200);

    $this->assertDatabaseMissing('order_carts', ['id' => $cartId]);

    // 5. Clear cart
    $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId1,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ])->assertStatus(201);

    $this->deleteJson('/api/user/cart/clear?uu_id='.$this->uuId1)
        ->assertStatus(200);

    $afterClear = $this->getJson('/api/user/cart?uu_id='.$this->uuId1)
        ->assertStatus(200);

    expect($afterClear->json('data'))->toBeEmpty();
});
