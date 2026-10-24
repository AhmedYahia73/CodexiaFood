<?php

use App\Models\Branch;
use App\Models\CaptainOrder;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Kitchen;
use App\Models\KitchenOrder;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\StartShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'status' => true,
        'location' => [
            ['lat' => 30.00, 'lng' => 31.00],
            ['lat' => 30.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 31.00],
        ],
    ]);

    $this->withHeaders([
        'X-Lat' => 30.5,
        'X-Lng' => 31.5,
    ]);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->hallTable = HallTable::create([
        'name' => 'Table #1',
        'code' => 'T101',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->kitchen = Kitchen::create([
        'name' => 'Main Kitchen',
        'user_name' => 'kitchen1',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->captain = CaptainOrder::create([
        'name' => 'Captain Samy',
        'user_name' => 'samy_captain',
        'password' => 'secret123',
        'status' => true,
    ]);

    $this->captainToken = JWTAuth::fromUser($this->captain);

    $this->parentCat = Category::create([
        'name' => ['ar' => 'مأكولات', 'en' => 'Food'],
        'image' => 'categories/food.png',
        'status' => true,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'برجر دجاج', 'en' => 'Chicken Burger'],
        'image' => 'products/burger.png',
        'category_id' => $this->parentCat->id,
        'price' => 120.00,
        'kitchen_id' => $this->kitchen->id,
        'status' => true,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Ali Cashier',
        'user_name' => 'ali_cashier',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);

    $this->cashierToken = JWTAuth::fromUser($this->cashierMan);

    $this->financialAccount = FinancialAccount::create([
        'name' => ['ar' => 'الخزينة النقدية', 'en' => 'Cash Treasury'],
        'status' => true,
        'balance' => 0.00,
        'branch_id' => $this->branch->id,
        'is_cash' => true,
    ]);

    $this->shift = StartShift::create([
        'start' => now()->subHour(),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'financial_account_id' => $this->financialAccount->id,
    ]);
});

test('captain can login with user_name and password via api/captain/login', function () {
    $response = $this->postJson('/api/captain/login', [
        'user_name' => 'samy_captain',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.guard', 'captain_order')
        ->assertJsonPath('data.user.id', $this->captain->id);

    expect($response->json('data.access_token'))->not->toBeNull();
});

test('captain login fails with invalid credentials', function () {
    $response = $this->postJson('/api/captain/login', [
        'user_name' => 'samy_captain',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('status', false);
});

test('captain can login via general auth/login endpoint specifying guard', function () {
    $response = $this->postJson('/api/auth/login', [
        'name' => 'samy_captain',
        'password' => 'secret123',
        'guard' => 'captain_order',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.guard', 'captain_order')
        ->assertJsonPath('data.user.id', $this->captain->id);
});

test('captain can access me, refresh token, and logout', function () {
    // me
    $this->withHeader('Authorization', 'Bearer '.$this->captainToken)
        ->getJson('/api/captain/me')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $this->captain->id);

    // refresh
    $refreshRes = $this->withHeader('Authorization', 'Bearer '.$this->captainToken)
        ->postJson('/api/captain/refresh')
        ->assertStatus(200)
        ->assertJsonPath('status', true);
    expect($refreshRes->json('data.access_token'))->not->toBeNull();

    // logout
    $this->withHeader('Authorization', 'Bearer '.$this->captainToken)
        ->postJson('/api/captain/logout')
        ->assertStatus(200)
        ->assertJsonPath('status', true);
});

test('captain can fetch categories, products, halls, and table info', function () {
    $headers = ['Authorization' => 'Bearer '.$this->captainToken];

    // Parent categories
    $this->withHeaders($headers)
        ->getJson('/api/captain/categories/parents')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');

    // Products
    $this->withHeaders($headers)
        ->getJson('/api/captain/products')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');

    // Product details
    $this->withHeaders($headers)
        ->getJson('/api/captain/products/'.$this->product->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $this->product->id);

    // Halls
    $this->withHeaders($headers)
        ->getJson('/api/captain/halls')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');

    // Table info
    $this->withHeaders($headers)
        ->getJson('/api/captain/table/'.$this->hallTable->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.table_code', 'T101');
});

test('captain can add product to table cart and it sets captain_order_id and dispatches to kitchen', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->captainToken)
        ->postJson('/api/captain/cart', [
            'table_code' => 'T101',
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'بدون شطة',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.captain_order_id', $this->captain->id)
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.total_final_price', 240);

    $cartId = $response->json('data.id');

    // Verify in database
    $cart = OrderCart::find($cartId);
    expect($cart)->not->toBeNull()
        ->and($cart->captain_order_id)->toBe($this->captain->id)
        ->and($cart->hall_table_id)->toBe($this->hallTable->id)
        ->and($cart->quantity)->toBe(2);

    // Verify KitchenOrder dispatch
    $kitchenOrder = KitchenOrder::where('order_cart_id', $cartId)->first();
    expect($kitchenOrder)->not->toBeNull()
        ->and($kitchenOrder->kitchen_id)->toBe($this->kitchen->id);
});

test('captain can view, update, and clear cart', function () {
    $cart = OrderCart::create([
        'module' => 'dinein',
        'product_id' => $this->product->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'branch_id' => $this->branch->id,
        'quantity' => 1,
    ]);

    $headers = ['Authorization' => 'Bearer '.$this->captainToken];

    // Index
    $this->withHeaders($headers)
        ->getJson('/api/captain/cart?table_id='.$this->hallTable->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');

    // Show
    $this->withHeaders($headers)
        ->getJson('/api/captain/cart/'.$cart->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $cart->id);

    // Update
    $this->withHeaders($headers)
        ->putJson('/api/captain/cart/'.$cart->id, [
            'quantity' => 4,
            'notes' => 'Updated notes',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.quantity', 4);

    expect($cart->fresh()->quantity)->toBe(4);

    // Clear
    $this->withHeaders($headers)
        ->deleteJson('/api/captain/cart/clear', [
            'table_id' => $this->hallTable->id,
        ])
        ->assertStatus(200);

    expect(OrderCart::where('hall_table_id', $this->hallTable->id)->count())->toBe(0);
});

test('public table cart accepts captain_order_id key', function () {
    $response = $this->postJson('/api/table/cart', [
        'table_code' => 'T101',
        'product_id' => $this->product->id,
        'quantity' => 1,
        'captain_order_id' => $this->captain->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.captain_order_id', $this->captain->id);

    $cartId = $response->json('data.id');
    $cart = OrderCart::find($cartId);
    expect($cart->captain_order_id)->toBe($this->captain->id);
});

test('cashier checkout captures captain_order_id from cart items or explicit input', function () {
    // 1. Cart item created with captain_order_id
    $cart = OrderCart::create([
        'module' => 'dinein',
        'product_id' => $this->product->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'branch_id' => $this->branch->id,
        'quantity' => 2,
    ]);

    // Cashier checks out this dinein table order without passing captain_order_id explicitly
    $response = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
            'financial_account_id' => $this->financialAccount->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    $orderId = $response->json('data.id');
    $order = Order::find($orderId);
    expect($order)->not->toBeNull()
        ->and($order->captain_order_id)->toBe($this->captain->id);

    // 2. Cashier checks out passing explicit captain_order_id
    $otherCaptain = CaptainOrder::create([
        'name' => 'Captain Tarek',
        'user_name' => 'tarek_captain',
        'password' => 'secret123',
        'status' => true,
    ]);

    $cart2 = OrderCart::create([
        'module' => 'dinein',
        'product_id' => $this->product->id,
        'hall_table_id' => $this->hallTable->id,
        'branch_id' => $this->branch->id,
        'quantity' => 1,
    ]);

    $resExplicit = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'dinein',
            'table_id' => $this->hallTable->id,
            'financial_account_id' => $this->financialAccount->id,
            'captain_order_id' => $otherCaptain->id,
        ]);

    $resExplicit->assertStatus(201);
    $order2 = Order::find($resExplicit->json('data.id'));
    expect($order2->captain_order_id)->toBe($otherCaptain->id);
});

test('kitchen markDone sets status to done and orders/done APIs return completed orders and carts', function () {
    $kitchenToken = auth('kitchen')->login($this->kitchen);

    // Create an order in preparing status
    $order = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'financial_account_id' => $this->financialAccount->id,
        'module' => 'dinein',
        'status' => 'preparing',
        'total' => 120.00,
        'final_price' => 120.00,
    ]);

    $op = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'price' => 120.00,
    ]);

    $kitchenOrder = KitchenOrder::create([
        'kitchen_id' => $this->kitchen->id,
        'order_id' => $order->id,
        'order_cart_id' => null,
    ]);

    // Mark as done in kitchen
    $this->withHeader('Authorization', 'Bearer '.$kitchenToken)
        ->postJson('/api/kitchen/orders/'.$kitchenOrder->id.'/done')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect($order->fresh()->status)->toBe('done');

    // Query kitchen finished orders via kitchen endpoint
    $resKitchenDone = $this->withHeader('Authorization', 'Bearer '.$kitchenToken)
        ->getJson('/api/kitchen/orders/done');

    $resKitchenDone->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.order_id', $order->id)
        ->assertJsonPath('data.0.status', 'done');

    // Query kitchen finished orders via captain endpoint
    auth('kitchen')->logout();
    JWTAuth::unsetToken();
    Auth::forgetGuards();

    $resCaptainDone = $this->withHeader('Authorization', 'Bearer '.$this->captainToken)
        ->getJson('/api/captain/orders/done');

    $resCaptainDone->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.order_id', $order->id)
        ->assertJsonPath('data.0.status', 'done')
        ->assertJsonPath('data.0.table.code', 'T101');
});

test('captain can list dinein orders and view order details', function () {
    $order = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'financial_account_id' => $this->financialAccount->id,
        'module' => 'dinein',
        'status' => 'done',
        'total' => 120.00,
        'final_price' => 120.00,
    ]);

    $headers = ['Authorization' => 'Bearer '.$this->captainToken];

    // List orders
    $this->withHeaders($headers)
        ->getJson('/api/captain/orders')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');

    // Show order
    $this->withHeaders($headers)
        ->getJson('/api/captain/orders/'.$order->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $order->id);
});

test('unauthenticated access to protected captain routes is rejected', function () {
    $this->getJson('/api/captain/me')->assertStatus(401);
    $this->getJson('/api/captain/cart')->assertStatus(401);
    $this->postJson('/api/captain/cart', [])->assertStatus(401);
    $this->getJson('/api/captain/orders/done')->assertStatus(401);
});

test('kitchen check-new returns orders count difference for notifications', function () {
    $kitchenToken = auth('kitchen')->login($this->kitchen);

    $order1 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'module' => 'dinein',
        'status' => 'preparing',
        'total' => 50,
        'final_price' => 50,
    ]);
    $order2 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'module' => 'dinein',
        'status' => 'preparing',
        'total' => 60,
        'final_price' => 60,
    ]);

    // Create 2 pending kitchen orders
    KitchenOrder::create(['kitchen_id' => $this->kitchen->id, 'order_id' => $order1->id]);
    KitchenOrder::create(['kitchen_id' => $this->kitchen->id, 'order_id' => $order2->id]);

    // 1. Client has 1 order loaded -> Difference should be 1 with 1 new order
    $resPost = $this->withHeader('Authorization', 'Bearer '.$kitchenToken)
        ->postJson('/api/kitchen/orders/check-new', ['count' => 1]);

    $resPost->assertStatus(200)
        ->assertJson([
            'status' => true,
            'difference' => 1,
        ])
        ->assertJsonCount(1, 'new_orders');

    // 2. Client has 2 orders loaded via GET -> Difference should be 0 and empty new_orders
    $resGet = $this->withHeader('Authorization', 'Bearer '.$kitchenToken)
        ->getJson('/api/kitchen/orders/check-new?count=2');

    $resGet->assertStatus(200)
        ->assertExactJson([
            'status' => true,
            'difference' => 0,
            'new_orders' => [],
        ]);
});

test('captain check-new returns ready kitchen orders count difference for notifications', function () {
    // Create 2 orders done in kitchen
    Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'module' => 'dinein',
        'status' => 'done',
        'total' => 100,
        'final_price' => 100,
    ]);
    Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'hall_table_id' => $this->hallTable->id,
        'captain_order_id' => $this->captain->id,
        'module' => 'dinein',
        'status' => 'done',
        'total' => 50,
        'final_price' => 50,
    ]);

    $headers = ['Authorization' => 'Bearer '.$this->captainToken];

    // 1. Captain app sends count = 0 -> difference is 2 with 2 new_orders
    $resPost = $this->withHeaders($headers)
        ->postJson('/api/captain/orders/check-new', ['count' => 0]);

    $resPost->assertStatus(200)
        ->assertJson([
            'status' => true,
            'difference' => 2,
        ])
        ->assertJsonCount(2, 'new_orders');

    // 2. Captain app sends count = 2 via GET -> difference is 0 and empty new_orders
    $resGet = $this->withHeaders($headers)
        ->getJson('/api/captain/orders/check-new?count=2');

    $resGet->assertStatus(200)
        ->assertExactJson([
            'status' => true,
            'difference' => 0,
            'new_orders' => [],
        ]);
});
