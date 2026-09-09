<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Discount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\StartShift;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع مدينة نصر', 'en' => 'Nasr City Branch'],
        'status' => true,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 3',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Hossam Cashier',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);

    // Open an active shift
    $this->shift = StartShift::create([
        'start' => now()->subHour(),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->hallTable = HallTable::create([
        'name' => 'Table #5',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    // 10% discount
    $this->discount = Discount::create([
        'name' => ['ar' => 'خصم 10%', 'en' => '10% Discount'],
        'type' => 'percentage',
        'amount' => 10.00,
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
        'name' => ['ar' => 'برجر كينج لحم', 'en' => 'King Beef Burger'],
        'description' => ['ar' => 'وصف لذيذ', 'en' => 'Delicious'],
        'price' => 100.00,
        'image' => 'products/king.jpg',
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
        'name' => ['ar' => 'حجم عائلي', 'en' => 'Family Size'],
        'product_id' => $this->product->id,
        'variation_id' => $this->variation->id,
        'price' => 50.00,
        'status' => true,
    ]);

    $this->addon = Addon::create([
        'name' => ['ar' => 'أصابع بطاطس', 'en' => 'French Fries'],
        'price' => 20.00,
        'image' => 'addons/fries.jpg',
    ]);
});

test('cashier checkout creates order and related tables from cart items and clears cart', function () {
    // Add item to cart: dinein, quantity = 2
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'سريع من فضلك',
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

    // Validation requires hall_table_id for dinein
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'dinein',
        ])
        ->assertStatus(422);

    // Successful checkout
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout?lang=ar', [
            'module' => 'dinein',
            'hall_table_id' => $this->hallTable->id,
            'note' => 'طلب صالة عاجل',
        ])
        ->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح من السلة',
        ]);

    $orderId = $response->json('data.id');

    // Verify order in database
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'module' => 'dinein',
        'is_pos' => true,
    ]);

    // Verify OrderProduct created with quantity = 2 and note
    $this->assertDatabaseHas('order_products', [
        'order_id' => $orderId,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'note' => 'سريع من فضلك',
    ]);

    // Verify variations, options, addons in DB
    $this->assertDatabaseHas('order_p_options', [
        'option_id' => $this->option->id,
    ]);
    $this->assertDatabaseHas('order_p_addons', [
        'addon_id' => $this->addon->id,
    ]);

    // Verify cart was cleared for this module and cashier
    $cartCount = OrderCart::where('cashier_id', $this->cashier->id)
        ->where('module', 'dinein')
        ->count();
    expect($cartCount)->toBe(0);
});

test('checkout validates required fields for delivery module', function () {
    // Add delivery item
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'delivery',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(201);

    // Missing address, phone, name
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'delivery',
        ])
        ->assertStatus(422);

    // Provide required delivery fields
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'delivery',
            'address' => 'شارع عباس العقاد عمارة 12',
            'phone' => '01234567890',
            'name' => 'أحمد سمير',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.module', 'delivery')
        ->assertJsonPath('data.address', 'شارع عباس العقاد عمارة 12');
});

test('checkout fails when cart is empty for requested module', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'takeaway',
        ])
        ->assertStatus(400)
        ->assertJson([
            'status' => false,
            'message' => 'السلة فارغة لهذا القسم',
        ]);
});
