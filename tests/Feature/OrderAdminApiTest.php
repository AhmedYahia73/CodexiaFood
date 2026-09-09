<?php

use App\Models\Addon;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin Manager',
        'password' => 'password123',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع التجمع', 'en' => 'Tagamoa Branch'],
        'status' => true,
    ]);

    $this->shift = Shift::create([
        'name' => ['ar' => 'وردية الصباح', 'en' => 'Morning Shift'],
        'start_time' => '08:00',
        'end_time' => '16:00',
        'branch_id' => $this->branch->id,
        'is_tomorrow' => false,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Kareem Cashier',
        'password' => 'password',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'shift_id' => $this->shift->id,
    ]);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->hallTable = HallTable::create([
        'name' => 'T1',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'وجبة برجر سوبريم', 'en' => 'Burger Supreme Meal'],
        'description' => ['ar' => 'وصف لذيذ', 'en' => 'Delicious description'],
        'image' => 'products/burger.jpg',
        'price' => 150.00,
    ]);

    $this->variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $this->product->id,
        'status' => true,
        'required' => true,
    ]);

    $this->option = Option::create([
        'name' => ['ar' => 'حجم كبير', 'en' => 'Large Size'],
        'product_id' => $this->product->id,
        'variation_id' => $this->variation->id,
        'price' => 25.00,
        'status' => true,
    ]);

    $this->addon = Addon::create([
        'name' => ['ar' => 'إضافة جبنة شيدر', 'en' => 'Extra Cheddar Cheese'],
        'image' => 'addons/cheese.jpg',
        'price' => 15.00,
    ]);
});

test('admin can fetch orders/select-options containing shifts, cashiers, tables, and products', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders/select-options')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'shifts',
                'branches',
                'cashiers',
                'cashier_men',
                'hall_tables',
                'products',
                'addons',
            ],
        ]);

    expect($response->json('data.shifts.0.id'))->toBe($this->shift->id);
    expect($response->json('data.products.0.id'))->toBe($this->product->id);
});

test('admin can fetch pos orders and online orders via dedicated routes', function () {
    $posOrder = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 190.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 190.00,
    ]);

    $onlineOrder = Order::create([
        'shift_id' => $this->shift->id,
        'module' => 'delivery',
        'is_pos' => false,
        'address' => '123 Cairo St',
        'phone' => '01012345678',
        'name' => 'John Doe',
        'total' => 200.00,
        'total_tax' => 20.00,
        'total_discount' => 0,
        'final_price' => 220.00,
    ]);

    // Test pos orders endpoint
    $posResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders/pos')
        ->assertStatus(200);

    expect(collect($posResponse->json('data'))->pluck('id')->all())->toContain($posOrder->id);
    expect(collect($posResponse->json('data'))->pluck('id')->all())->not->toContain($onlineOrder->id);

    // Test online orders endpoint
    $onlineResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders/online')
        ->assertStatus(200);

    expect(collect($onlineResponse->json('data'))->pluck('id')->all())->toContain($onlineOrder->id);
    expect(collect($onlineResponse->json('data'))->pluck('id')->all())->not->toContain($posOrder->id);
});

test('admin can create full order with nested products, variations, options, and addons', function () {
    $payload = [
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 190.00,
        'total_tax' => 10.00,
        'total_discount' => 5.00,
        'final_price' => 195.00,
        'products' => [
            [
                'product_id' => $this->product->id,
                'price' => 150.00,
                'note' => 'بدون طماطم',
                'variations' => [
                    [
                        'variation_id' => $this->variation->id,
                        'options' => [
                            [
                                'option_id' => $this->option->id,
                                'price' => 25.00,
                            ],
                        ],
                    ],
                ],
                'addons' => [
                    [
                        'addon_id' => $this->addon->id,
                        'price' => 15.00,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.final_price', 195);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('data.id'),
        'final_price' => 195.00,
    ]);

    $this->assertDatabaseHas('order_products', [
        'order_id' => $response->json('data.id'),
        'product_id' => $this->product->id,
        'note' => 'بدون طماطم',
    ]);

    $this->assertDatabaseHas('order_p_options', [
        'option_id' => $this->option->id,
        'price' => 25.00,
    ]);

    $this->assertDatabaseHas('order_p_addons', [
        'addon_id' => $this->addon->id,
        'price' => 15.00,
    ]);
});

test('admin receives properly localized names in arabic when requested', function () {
    $order = Order::create([
        'shift_id' => $this->shift->id,
        'module' => 'takeaway',
        'is_pos' => true,
        'total' => 190.00,
        'final_price' => 190.00,
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'price' => 150.00,
    ]);

    $orderVariation = OrderPVariation::create([
        'order_product_id' => $orderProduct->id,
        'variation_id' => $this->variation->id,
    ]);

    OrderPOption::create([
        'order_p_variation_id' => $orderVariation->id,
        'option_id' => $this->option->id,
        'price' => 25.00,
    ]);

    OrderPAddon::create([
        'order_product_id' => $orderProduct->id,
        'addon_id' => $this->addon->id,
        'price' => 15.00,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/admin/orders/'.$order->id)
        ->assertStatus(200);

    // Verify Arabic translations are returned directly in the fields
    expect($response->json('data.shift_name'))->toBe('وردية الصباح');
    expect($response->json('data.products.0.name'))->toBe('وجبة برجر سوبريم');
    expect($response->json('data.products.0.variations.0.name'))->toBe('الحجم');
    expect($response->json('data.products.0.variations.0.options.0.name'))->toBe('حجم كبير');
    expect($response->json('data.products.0.addons.0.name'))->toBe('إضافة جبنة شيدر');
});

test('admin receives properly localized names in english when requested', function () {
    $order = Order::create([
        'shift_id' => $this->shift->id,
        'module' => 'takeaway',
        'is_pos' => true,
        'total' => 190.00,
        'final_price' => 190.00,
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'price' => 150.00,
    ]);

    $orderVariation = OrderPVariation::create([
        'order_product_id' => $orderProduct->id,
        'variation_id' => $this->variation->id,
    ]);

    OrderPOption::create([
        'order_p_variation_id' => $orderVariation->id,
        'option_id' => $this->option->id,
        'price' => 25.00,
    ]);

    OrderPAddon::create([
        'order_product_id' => $orderProduct->id,
        'addon_id' => $this->addon->id,
        'price' => 15.00,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/admin/orders/'.$order->id)
        ->assertStatus(200);

    // Verify English translations are returned directly in the fields
    expect($response->json('data.shift_name'))->toBe('Morning Shift');
    expect($response->json('data.products.0.name'))->toBe('Burger Supreme Meal');
    expect($response->json('data.products.0.variations.0.name'))->toBe('Size');
    expect($response->json('data.products.0.variations.0.options.0.name'))->toBe('Large Size');
    expect($response->json('data.products.0.addons.0.name'))->toBe('Extra Cheddar Cheese');
});
