<?php

use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\BusinessSetup;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\FinancialAccount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\StartShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع التجمع', 'en' => 'Tagamoa Branch'],
        'status' => true,
    ]);

    $this->businessSetup = BusinessSetup::create([
        'name' => 'Codexa Restaurant',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business/logo.png',
        'description' => 'Codexa description',
        'table_order_url' => 'https://menu.codexaecommerce.com',
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Cashier Operator',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);

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
        'name' => 'Table #1',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->financialAccount = FinancialAccount::create([
        'name' => ['ar' => 'الخزينة الرئيسية', 'en' => 'Main Treasury'],
        'status' => true,
        'branch_id' => $this->branch->id,
        'balance' => 500.00,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'وجبة دجاج', 'en' => 'Chicken Meal'],
        'image' => 'products/chicken.jpg',
        'price' => 150.00,
    ]);
});

test('order automatically generates uuid code upon creation', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
    ]);

    expect($order->code)->not->toBeNull();
    expect(Str::isUuid($order->code))->toBeTrue();
});

test('order retains custom code if explicitly provided', function () {
    $customUuid = (string) Str::uuid();

    $order = Order::create([
        'code' => $customUuid,
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
    ]);

    expect($order->code)->toBe($customUuid);
});

test('order resource includes code and qr urls', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
    ]);

    $resource = (new OrderResource($order))->toArray(request());

    expect($resource)->toHaveKey('code');
    expect($resource['code'])->toBe($order->code);
    expect($resource)->toHaveKey('qr');
    expect($resource['qr'])->toContain('/api/orders/'.$order->code.'/qr');
    expect($resource)->toHaveKey('order_tracking_url');
    expect($resource['order_tracking_url'])->toBe('https://menu.codexaecommerce.com/my_order/'.$order->code);
});

test('cashier checkout returns qr, qr_url, qr_base64 and tracking url', function () {
    // Add cart item
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertStatus(201);

    // Checkout
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'takeaway',
            'financial_account_id' => $this->financialAccount->id,
        ])
        ->assertStatus(201);

    $response->assertJsonStructure([
        'status',
        'message',
        'qr',
        'qr_url',
        'qr_base64',
        'my_order_url',
        'data' => [
            'id',
            'code',
            'qr',
            'qr_url',
        ],
    ]);

    $code = $response->json('data.code');
    expect(Str::isUuid($code))->toBeTrue();
    expect($response->json('qr'))->toContain('/api/orders/'.$code.'/qr');
    expect($response->json('qr_url'))->toContain('/api/orders/'.$code.'/qr');
    expect($response->json('qr_base64'))->toStartWith('data:image/svg+xml;base64,');
    expect($response->json('my_order_url'))->toBe('https://menu.codexaecommerce.com/my_order/'.$code);
});

test('public api returns order details by code without authentication', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
        'name' => 'Mohamed Ali',
        'phone' => '01099999999',
    ]);

    $response = $this->getJson('/api/orders/'.$order->code);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $order->id,
                'code' => $order->code,
                'name' => 'Mohamed Ali',
                'phone' => '01099999999',
            ],
        ]);
});

test('public api alias my_order returns order details by code without authentication', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
        'name' => 'Ahmed Hassan',
    ]);

    $response = $this->getJson('/api/my_order/'.$order->code);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $order->id,
                'code' => $order->code,
                'name' => 'Ahmed Hassan',
            ],
        ]);
});

test('public api qr endpoint returns dynamic svg image stream without authentication', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'total' => 150.00,
        'final_price' => 150.00,
    ]);

    $response = $this->get('/api/orders/'.$order->code.'/qr');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->getContent())->toContain('<svg');
});

test('public api returns 404 for non existent order code', function () {
    $response = $this->getJson('/api/orders/non-existent-uuid-123');

    $response->assertStatus(404)
        ->assertJson([
            'status' => false,
            'message' => 'الطلب غير موجود',
        ]);
});
