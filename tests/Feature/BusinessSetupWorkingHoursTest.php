<?php

use App\Models\Admin;
use App\Models\BusinessSetup;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin Test',
        'password' => 'password123',
    ]);
    $this->token = JWTAuth::fromUser($this->admin);

    $this->businessSetup = BusinessSetup::create([
        'name' => 'مطعم كودكسا التجريبي',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/test',
        'instagram' => 'https://instagram.com/test',
        'whats' => '01012345678',
        'logo' => 'business_setup/test.png',
        'description' => 'وصف المطعم',
        'branch_cover' => 10.00,
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'برجر جبنة', 'en' => 'Cheese Burger'],
        'description' => ['ar' => 'برجر لذيذ', 'en' => 'Delicious burger'],
        'image' => 'products/burger.jpg',
        'price' => 100.00,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('admin can update business setup with start_day and end_day', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/admin/business-setup', [
            'name' => 'مطعم كودكسا المحدث',
            'phone' => '01099999999',
            'face' => 'https://facebook.com/updated',
            'instagram' => 'https://instagram.com/updated',
            'whats' => '01099999999',
            'logo' => 'business_setup/test.png',
            'description' => 'وصف محدث',
            'start_day' => '10:00',
            'end_day' => '02:00',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.start_day', '10:00')
        ->assertJsonPath('data.end_day', '02:00')
        ->assertJsonPath('data.is_overnight', true);

    expect($this->businessSetup->fresh()->start_day)->toBe('10:00:00')
        ->and($this->businessSetup->fresh()->end_day)->toBe('02:00:00');
});

test('overnight working hours logic correctly identifies open and closed times', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // 02:00 AM (Next calendar day, but inside current overnight shift) -> OPEN
    Carbon::setTestNow(Carbon::parse('2026-10-04 02:00:00'));
    expect($this->businessSetup->isOpen())->toBeTrue();

    // 03:00 AM (Closing minute) -> OPEN
    Carbon::setTestNow(Carbon::parse('2026-10-04 03:00:00'));
    expect($this->businessSetup->isOpen())->toBeTrue();

    // 03:01 AM -> CLOSED
    Carbon::setTestNow(Carbon::parse('2026-10-04 03:01:00'));
    expect($this->businessSetup->isOpen())->toBeFalse();

    // 05:00 AM -> CLOSED
    Carbon::setTestNow(Carbon::parse('2026-10-04 05:00:00'));
    expect($this->businessSetup->isOpen())->toBeFalse();

    // 08:59:59 AM -> CLOSED
    Carbon::setTestNow(Carbon::parse('2026-10-04 08:59:59'));
    expect($this->businessSetup->isOpen())->toBeFalse();

    // 09:00 AM -> OPEN
    Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00'));
    expect($this->businessSetup->isOpen())->toBeTrue();

    // 14:00 PM -> OPEN
    Carbon::setTestNow(Carbon::parse('2026-10-04 14:00:00'));
    expect($this->businessSetup->isOpen())->toBeTrue();

    // 23:59 PM -> OPEN
    Carbon::setTestNow(Carbon::parse('2026-10-04 23:59:00'));
    expect($this->businessSetup->isOpen())->toBeTrue();
});

test('user cannot add, edit, or delete cart items outside working hours', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Freeze time at 05:00 AM (closed)
    Carbon::setTestNow(Carbon::parse('2026-10-04 05:00:00'));

    $cart = OrderCart::create([
        'uu_id' => 'user-test-uuid',
        'module' => 'delivery',
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);

    // 1. Add to cart
    $storeResponse = $this->postJson('/api/user/cart', [
        'uu_id' => 'user-test-uuid',
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);
    $storeResponse->assertStatus(400)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'المطعم مغلق الان مواعيد العمل من 09:00 الى 03:00');

    // 2. Edit cart
    $updateResponse = $this->putJson('/api/user/cart/'.$cart->id, [
        'uu_id' => 'user-test-uuid',
        'quantity' => 3,
    ]);
    $updateResponse->assertStatus(400)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'المطعم مغلق الان مواعيد العمل من 09:00 الى 03:00');

    // 3. Delete single cart item
    $destroyResponse = $this->deleteJson('/api/user/cart/'.$cart->id.'?uu_id=user-test-uuid');
    $destroyResponse->assertStatus(400)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'المطعم مغلق الان مواعيد العمل من 09:00 الى 03:00');

    // 4. Clear cart
    $clearResponse = $this->deleteJson('/api/user/cart/clear?uu_id=user-test-uuid');
    $clearResponse->assertStatus(400)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'المطعم مغلق الان مواعيد العمل من 09:00 الى 03:00');
});

test('user can mutate cart at 02:00 AM when working hours extend overnight to 03:00 AM', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Freeze time at 02:00 AM (open)
    Carbon::setTestNow(Carbon::parse('2026-10-04 02:00:00'));

    $response = $this->postJson('/api/user/cart', [
        'uu_id' => 'user-overnight-uuid',
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);
});

test('user checkout is blocked outside working hours with closed message', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Freeze time at 05:00 AM (closed)
    Carbon::setTestNow(Carbon::parse('2026-10-04 05:00:00'));

    $response = $this->postJson('/api/user/orders/checkout', [
        'uu_id' => 'user-test-uuid',
        'name' => 'أحمد العميل',
        'phone' => '01012345678',
        'address' => 'شارع التحرير',
        'lat' => 30.0444,
        'lng' => 31.2357,
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'المطعم مغلق الان مواعيد العمل من 09:00 الى 03:00');
});

test('admin orders index fetches today and yesterday business days by default', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Simulate current time: Saturday 2026-10-04 at 02:00:00 AM
    // Today's business day: started Friday 2026-10-03 at 09:00:00 AM
    // Yesterday's business day: started Thursday 2026-10-02 at 09:00:00 AM
    Carbon::setTestNow(Carbon::parse('2026-10-04 02:00:00'));

    // Order 1: Thursday 08:30 AM (Older than yesterday's business shift start 09:00) -> Should NOT appear
    $oldOrder = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
    ]);
    DB::table('orders')->where('id', $oldOrder->id)->update(['created_at' => '2026-10-02 08:30:00']);

    // Order 2: Thursday 11:00 AM (Yesterday's business day) -> Should appear
    $yesterdayOrder = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 150,
        'final_price' => 150,
    ]);
    DB::table('orders')->where('id', $yesterdayOrder->id)->update(['created_at' => '2026-10-02 11:00:00']);

    // Order 3: Friday 10:00 PM (Today's business day) -> Should appear
    $todayOrder = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 200,
        'final_price' => 200,
    ]);
    DB::table('orders')->where('id', $todayOrder->id)->update(['created_at' => '2026-10-03 22:00:00']);

    // Order 4: Saturday 01:30 AM (30 minutes ago, inside today's business day) -> Should appear
    $recentOrder = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 250,
        'final_price' => 250,
    ]);
    DB::table('orders')->where('id', $recentOrder->id)->update(['created_at' => '2026-10-04 01:30:00']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($yesterdayOrder->id)
        ->and($ids)->toContain($todayOrder->id)
        ->and($ids)->toContain($recentOrder->id)
        ->and($ids)->not->toContain($oldOrder->id);
});

test('admin orders check-new endpoint compares client count and returns extra order_ids for online orders', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Current time: Saturday 2026-10-04 at 02:00:00 AM
    Carbon::setTestNow(Carbon::parse('2026-10-04 02:00:00'));

    // Create 3 online orders within today & yesterday window
    $order1 = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
        'created_at' => Carbon::parse('2026-10-03 12:00:00'),
    ]);
    $order2 = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 150,
        'final_price' => 150,
        'created_at' => Carbon::parse('2026-10-03 18:00:00'),
    ]);
    $order3 = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 200,
        'final_price' => 200,
        'created_at' => Carbon::parse('2026-10-04 01:00:00'),
    ]);

    // Create 1 POS order (must be ignored by check-new because is_pos = true)
    $posOrder = Order::create([
        'module' => 'dinein',
        'is_pos' => true,
        'total' => 300,
        'final_price' => 300,
        'created_at' => Carbon::parse('2026-10-04 01:15:00'),
    ]);

    // Client says they currently have 1 order
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders/check-new', [
            'count' => 1,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('client_count', 1)
        ->assertJsonPath('server_count', 3)
        ->assertJsonPath('difference', 2)
        ->assertJsonPath('has_new', true);

    $extraIds = $response->json('order_ids');
    expect($extraIds)->toHaveCount(2)
        ->and($extraIds)->toContain($order3->id)
        ->and($extraIds)->toContain($order2->id)
        ->and($extraIds)->not->toContain($posOrder->id);

    // If client has all 3 orders
    $upToDateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders/check-new?count=3');

    $upToDateResponse->assertStatus(200)
        ->assertJsonPath('difference', 0)
        ->assertJsonPath('has_new', false)
        ->assertJsonPath('order_ids', []);
});

test('daytime working hours (non-overnight) correctly verifies open status', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '22:00:00',
    ]);

    expect($this->businessSetup->fresh()->isOvernight())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00'));
    expect($this->businessSetup->fresh()->isOpen())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-04 23:00:00'));
    expect($this->businessSetup->fresh()->isOpen())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-04 08:00:00'));
    expect($this->businessSetup->fresh()->isOpen())->toBeFalse();
});

test('admin orders index supports explicit date filter according to business hours', function () {
    $this->businessSetup->update([
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ]);

    // Order on Oct 10 10:00 AM (inside Oct 10 business day)
    $orderOct10 = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
    ]);
    DB::table('orders')->where('id', $orderOct10->id)->update(['created_at' => '2026-10-10 10:00:00']);

    // Order on Oct 11 02:00 AM (inside Oct 10 business day overnight)
    $orderOct11Early = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 120,
        'final_price' => 120,
    ]);
    DB::table('orders')->where('id', $orderOct11Early->id)->update(['created_at' => '2026-10-11 02:00:00']);

    // Order on Oct 11 05:00 PM (belongs to Oct 11 business day)
    $orderOct11Evening = Order::create([
        'module' => 'delivery',
        'is_pos' => false,
        'total' => 150,
        'final_price' => 150,
    ]);
    DB::table('orders')->where('id', $orderOct11Evening->id)->update(['created_at' => '2026-10-11 17:00:00']);

    // Filter specifically for date=2026-10-10
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/orders?date=2026-10-10')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($orderOct10->id)
        ->and($ids)->toContain($orderOct11Early->id)
        ->and($ids)->not->toContain($orderOct11Evening->id);
});
