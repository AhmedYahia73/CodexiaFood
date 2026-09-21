<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'password' => 'password123',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);
});

test('unauthenticated users cannot access dashboard statistics', function () {
    $this->getJson('/api/admin/dashboard')
        ->assertStatus(401);
});

test('admin can fetch annual dashboard statistics defaulting to current year', function () {
    $currentYear = (int) now()->year;

    // Create 3 products
    $product1 = Product::create([
        'name' => ['ar' => 'برجر دجاج', 'en' => 'Chicken Burger'],
        'image' => 'products/chicken.jpg',
        'price' => 100.00,
    ]);
    $product2 = Product::create([
        'name' => ['ar' => 'بيتزا', 'en' => 'Pizza'],
        'image' => 'products/pizza.jpg',
        'price' => 150.00,
    ]);
    $product3 = Product::create([
        'name' => ['ar' => 'بطاطس', 'en' => 'Fries'],
        'image' => 'products/fries.jpg',
        'price' => 50.00,
    ]);

    // Order 1 in current year, month 3 (March)
    $order1 = Order::create([
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 350.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 350.00,
    ]);
    DB::table('orders')->where('id', $order1->id)->update(['created_at' => Carbon::create($currentYear, 3, 15, 12, 0, 0)]);

    OrderProduct::create([
        'order_id' => $order1->id,
        'product_id' => $product1->id,
        'quantity' => 2,
        'price' => 100.00,
    ]);
    OrderProduct::create([
        'order_id' => $order1->id,
        'product_id' => $product2->id,
        'quantity' => 1,
        'price' => 150.00,
    ]);

    // Order 2 in current year, month 3 (March)
    $order2 = Order::create([
        'module' => 'takeaway',
        'is_pos' => true,
        'total' => 200.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 200.00,
    ]);
    DB::table('orders')->where('id', $order2->id)->update(['created_at' => Carbon::create($currentYear, 3, 20, 14, 0, 0)]);

    OrderProduct::create([
        'order_id' => $order2->id,
        'product_id' => $product1->id,
        'quantity' => 5,
        'price' => 100.00,
    ]);

    // Order 3 in current year, month 7 (July)
    $order3 = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 150.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 150.00,
    ]);
    DB::table('orders')->where('id', $order3->id)->update(['created_at' => Carbon::create($currentYear, 7, 5, 18, 0, 0)]);

    OrderProduct::create([
        'order_id' => $order3->id,
        'product_id' => $product3->id,
        'quantity' => 3,
        'price' => 50.00,
    ]);

    // Order in past year (should be excluded)
    $pastOrder = Order::create([
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 500.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 500.00,
    ]);
    DB::table('orders')->where('id', $pastOrder->id)->update(['created_at' => Carbon::create($currentYear - 1, 5, 10, 10, 0, 0)]);

    OrderProduct::create([
        'order_id' => $pastOrder->id,
        'product_id' => $product2->id,
        'quantity' => 10,
        'price' => 150.00,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'year' => $currentYear,
                'total_orders' => 3,
                'total_final_price' => 700.00,
            ],
        ]);

    $data = $response->json('data');

    // Check top products: product1 total quantity = 2 + 5 = 7; product3 = 3; product2 = 1
    expect($data['top_products'])->toHaveCount(3);
    expect($data['top_products'][0]['product_id'])->toBe($product1->id);
    expect($data['top_products'][0]['total_quantity'])->toBe(7);
    expect($data['top_products'][1]['product_id'])->toBe($product3->id);
    expect($data['top_products'][1]['total_quantity'])->toBe(3);
    expect($data['top_products'][2]['product_id'])->toBe($product2->id);
    expect($data['top_products'][2]['total_quantity'])->toBe(1);

    // Check monthly orders count: all 12 months must exist
    expect($data['monthly_orders'])->toHaveCount(12);
    // Month 3 (March) has 2 orders
    expect($data['monthly_orders'][2]['month'])->toBe(3);
    expect($data['monthly_orders'][2]['orders_count'])->toBe(2);
    // Month 7 (July) has 1 order
    expect($data['monthly_orders'][6]['month'])->toBe(7);
    expect($data['monthly_orders'][6]['orders_count'])->toBe(1);
    // Month 1 (January) has 0 orders
    expect($data['monthly_orders'][0]['month'])->toBe(1);
    expect($data['monthly_orders'][0]['orders_count'])->toBe(0);

    // Check monthly final price: all 12 months must exist
    expect($data['monthly_final_price'])->toHaveCount(12);
    // Month 3 sum: 350 + 200 = 550.00
    expect($data['monthly_final_price'][2]['total_final_price'])->toEqual(550.0);
    // Month 7 sum: 150.00
    expect($data['monthly_final_price'][6]['total_final_price'])->toEqual(150.0);
    // Month 1 sum: 0.00
    expect($data['monthly_final_price'][0]['total_final_price'])->toEqual(0.0);
});

test('admin can filter dashboard statistics by specific year', function () {
    $targetYear = 2024;

    $order = Order::create([
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 1250.50,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 1250.50,
    ]);
    DB::table('orders')->where('id', $order->id)->update(['created_at' => Carbon::create($targetYear, 6, 1, 10, 0, 0)]);

    $product = Product::create([
        'name' => ['ar' => 'وجبة عائلية', 'en' => 'Family Meal'],
        'image' => 'products/family.jpg',
        'price' => 1250.50,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 1250.50,
        'created_at' => Carbon::create($targetYear, 6, 1, 10, 0, 0),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/dashboard?year='.$targetYear)
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'year' => $targetYear,
                'total_orders' => 1,
                'total_final_price' => 1250.50,
            ],
        ]);

    $data = $response->json('data');
    expect($data['monthly_orders'][5]['orders_count'])->toBe(1);
    expect($data['monthly_final_price'][5]['total_final_price'])->toEqual(1250.50);
});

test('top products only returns up to 5 items sorted by total quantity descending', function () {
    $currentYear = (int) now()->year;

    $order = Order::create([
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 1000.00,
        'total_tax' => 0,
        'total_discount' => 0,
        'final_price' => 1000.00,
    ]);
    DB::table('orders')->where('id', $order->id)->update(['created_at' => Carbon::create($currentYear, 1, 1, 10, 0, 0)]);

    for ($i = 1; $i <= 7; $i++) {
        $product = Product::create([
            'name' => ['ar' => "منتج {$i}", 'en' => "Product {$i}"],
            'image' => "products/{$i}.jpg",
            'price' => 10.00,
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $i * 10,
            'price' => 10.00,
        ]);
    }

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/dashboard')
        ->assertStatus(200);

    $topProducts = $response->json('data.top_products');
    expect($topProducts)->toHaveCount(5);
    expect($topProducts[0]['total_quantity'])->toBe(70);
    expect($topProducts[1]['total_quantity'])->toBe(60);
    expect($topProducts[2]['total_quantity'])->toBe(50);
    expect($topProducts[3]['total_quantity'])->toBe(40);
    expect($topProducts[4]['total_quantity'])->toBe(30);
});

test('admin can access dashboard statistics via alias route dashboard/statistics', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/dashboard/statistics')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
        ]);
});
