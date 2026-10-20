<?php

use App\Http\Resources\OrderResource;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Delivery;
use App\Models\FinancialAccount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'password' => 'password123',
    ]);
    $this->token = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create(['name' => 'Main Branch']);

    $this->delivery = Delivery::create([
        'name' => 'Captain Fast',
        'phone' => '01012345678',
        'branch_id' => $this->branch->id,
    ]);

    $this->paymentMethodCash = PaymentMethod::create([
        'name' => ['en' => 'Cash on Delivery', 'ar' => 'دفع عند الاستلام'],
        'status' => true,
        'is_cash' => true,
    ]);

    $this->paymentMethodVisa = PaymentMethod::create([
        'name' => ['en' => 'Online Visa', 'ar' => 'فيزا أونلاين'],
        'status' => true,
        'is_cash' => false,
    ]);

    $this->financialAccountCash = FinancialAccount::create([
        'name' => ['en' => 'Cash Drawer', 'ar' => 'درج النقدية'],
        'balance' => 0,
        'status' => true,
        'is_cash' => true,
        'branch_id' => $this->branch->id,
    ]);

    $this->financialAccountBank = FinancialAccount::create([
        'name' => ['en' => 'Bank Account', 'ar' => 'حساب بنكي'],
        'balance' => 0,
        'status' => true,
        'is_cash' => false,
        'branch_id' => $this->branch->id,
    ]);
});

test('paid is false only if financial account or payment method is_cash true and module is delivery, else true', function () {
    // 1. Delivery order with cash financial account => paid should be false
    $cashDeliveryOrder = Order::create([
        'module' => 'delivery',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($cashDeliveryOrder->paid)->toBeFalse();

    // 2. Takeaway order with cash financial account => paid should be true (payment collected at counter)
    $cashTakeawayOrder = Order::create([
        'module' => 'takeaway',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($cashTakeawayOrder->paid)->toBeTrue();

    // 3. Dinein order with cash financial account => paid should be true
    $cashDineinOrder = Order::create([
        'module' => 'dinein',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($cashDineinOrder->paid)->toBeTrue();

    // 4. Delivery order with bank financial account (non-cash) => paid should be true
    $bankDeliveryOrder = Order::create([
        'module' => 'delivery',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountBank->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($bankDeliveryOrder->paid)->toBeTrue();

    // 5. Online delivery order with cash payment method => paid should be false
    $onlineCashOrder = Order::create([
        'module' => 'delivery',
        'branch_id' => $this->branch->id,
        'payment_method_id' => $this->paymentMethodCash->id,
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($onlineCashOrder->paid)->toBeFalse();

    // 6. Online takeaway order with cash payment method => paid should be true
    $onlineCashTakeawayOrder = Order::create([
        'module' => 'takeaway',
        'branch_id' => $this->branch->id,
        'payment_method_id' => $this->paymentMethodCash->id,
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($onlineCashTakeawayOrder->paid)->toBeTrue();

    // 7. Online delivery order with visa payment method => paid should be true
    $onlineVisaOrder = Order::create([
        'module' => 'delivery',
        'branch_id' => $this->branch->id,
        'payment_method_id' => $this->paymentMethodVisa->id,
        'is_pos' => false,
        'total' => 100,
        'final_price' => 100,
    ]);
    expect($onlineVisaOrder->paid)->toBeTrue();
});

test('validation requires financial_account_id when is_pos is true', function () {
    $product = Product::create([
        'name' => ['en' => 'Burger', 'ar' => 'برجر'],
        'image' => 'products/burger.png',
        'price' => 100,
        'status' => true,
    ]);

    // Attempt to create POS order without financial_account_id
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders', [
            'is_pos' => true,
            'module' => 'takeaway',
            'total' => 100,
            'final_price' => 100,
            'products' => [
                ['product_id' => $product->id, 'price' => 100, 'quantity' => 1],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['financial_account_id']);
});

test('validation requires payment_method_id when is_pos is false', function () {
    $product = Product::create([
        'name' => ['en' => 'Burger', 'ar' => 'برجر'],
        'image' => 'products/burger.png',
        'price' => 100,
        'status' => true,
    ]);

    // Attempt to create online order without payment_method_id
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders', [
            'is_pos' => false,
            'module' => 'delivery',
            'total' => 100,
            'final_price' => 100,
            'products' => [
                ['product_id' => $product->id, 'price' => 100, 'quantity' => 1],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method_id']);
});

test('admin can store order with auto-calculated paid status based on is_cash', function () {
    $product = Product::create([
        'name' => ['en' => 'Burger', 'ar' => 'برجر'],
        'image' => 'products/burger.png',
        'price' => 100,
        'status' => true,
    ]);

    // 1. POS order with cash financial account -> paid should be false
    $posCashRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders', [
            'is_pos' => true,
            'module' => 'delivery',
            'financial_account_id' => $this->financialAccountCash->id,
            'delivery_id' => $this->delivery->id,
            'total' => 100,
            'final_price' => 100,
            'products' => [
                ['product_id' => $product->id, 'price' => 100, 'quantity' => 1],
            ],
        ]);

    $posCashRes->assertStatus(201)
        ->assertJsonPath('data.paid', false)
        ->assertJsonPath('data.financial_account_id', $this->financialAccountCash->id);

    // 2. Online order with visa payment method -> paid should be true
    $onlineVisaRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/orders', [
            'is_pos' => false,
            'module' => 'delivery',
            'payment_method_id' => $this->paymentMethodVisa->id,
            'delivery_id' => $this->delivery->id,
            'total' => 100,
            'final_price' => 100,
            'products' => [
                ['product_id' => $product->id, 'price' => 100, 'quantity' => 1],
            ],
        ]);

    $onlineVisaRes->assertStatus(201)
        ->assertJsonPath('data.paid', true)
        ->assertJsonPath('data.payment_method_id', $this->paymentMethodVisa->id);
});

test('order resource exposes delivery_id, delivery, payment_method_id, payment_method, and paid', function () {
    $order = Order::create([
        'module' => 'delivery',
        'branch_id' => $this->branch->id,
        'delivery_id' => $this->delivery->id,
        'payment_method_id' => $this->paymentMethodCash->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'total' => 150,
        'final_price' => 150,
    ]);

    $order->load(['delivery', 'paymentMethod']);

    $resource = (new OrderResource($order))->toArray(Request::create('/api/admin/orders'));

    expect($resource['delivery_id'])->toBe($this->delivery->id);
    expect($resource['delivery'])->not->toBeNull();
    expect($resource['delivery']['id'])->toBe($this->delivery->id);
    expect($resource['payment_method_id'])->toBe($this->paymentMethodCash->id);
    expect($resource['payment_method'])->not->toBeNull();
    expect($resource['payment_method']['id'])->toBe($this->paymentMethodCash->id);
    expect($resource['paid'])->toBeFalse();
});

test('admin can fetch deliveries select returning strictly id and name', function () {
    $res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/deliveries/select');

    $res->assertStatus(200)
        ->assertJsonPath('status', true);

    $data = $res->json('data');
    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $item = $data[0];
    expect(array_keys($item))->toEqualCanonicalizing(['id', 'name']);
});

test('changing order status to out_of_delivery requires delivery_id when module is delivery', function () {
    $deliveryOrder = Order::create([
        'module' => 'delivery',
        'status' => 'done',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);

    // 1. Without delivery_id -> validation error 422
    $failRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/orders/{$deliveryOrder->id}/status", [
            'status' => 'out_of_delivery',
        ]);

    $failRes->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_id']);

    // 2. With delivery_id -> success 200, updates status and delivery_id in orders table
    $successRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/orders/{$deliveryOrder->id}/status", [
            'status' => 'out_of_delivery',
            'delivery_id' => $this->delivery->id,
        ]);

    $successRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.status', 'out_of_delivery')
        ->assertJsonPath('data.delivery_id', $this->delivery->id);

    $this->assertDatabaseHas('orders', [
        'id' => $deliveryOrder->id,
        'status' => 'out_of_delivery',
        'delivery_id' => $this->delivery->id,
    ]);
});

test('changing order status for takeaway module does not require delivery_id', function () {
    $takeawayOrder = Order::create([
        'module' => 'takeaway',
        'status' => 'done',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/orders/{$takeawayOrder->id}/status", [
            'status' => 'pickup',
        ]);

    $res->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.status', 'pickup');
});

test('cashier can fetch deliveries select and update order status with delivery_id', function () {
    $cashierMan = CashierMan::create([
        'name' => 'Cashier Man',
        'user_name' => 'cashier_user',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
    ]);
    $cashierToken = JWTAuth::fromUser($cashierMan);

    // 1. Cashier fetch deliveries select
    $delRes = $this->withHeader('Authorization', 'Bearer '.$cashierToken)
        ->getJson('/api/cashier/deliveries/select');

    $delRes->assertStatus(200)
        ->assertJsonPath('status', true);

    $data = $delRes->json('data');
    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();
    expect(array_keys($data[0]))->toEqualCanonicalizing(['id', 'name']);

    // 2. Cashier update status to out_of_delivery
    $deliveryOrder = Order::create([
        'module' => 'delivery',
        'status' => 'done',
        'branch_id' => $this->branch->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);

    $updateRes = $this->withHeader('Authorization', 'Bearer '.$cashierToken)
        ->putJson("/api/cashier/orders/{$deliveryOrder->id}/status", [
            'status' => 'out_of_delivery',
            'delivery_id' => $this->delivery->id,
        ]);

    $updateRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.status', 'out_of_delivery')
        ->assertJsonPath('data.delivery_id', $this->delivery->id);

    $this->assertDatabaseHas('orders', [
        'id' => $deliveryOrder->id,
        'status' => 'out_of_delivery',
        'delivery_id' => $this->delivery->id,
    ]);
});

test('cashier can list unpaid delivery orders with pagination and delivery_id filter', function () {
    $shift = Shift::create([
        'name' => ['en' => 'Morning', 'ar' => 'Morning'],
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'branch_id' => $this->branch->id,
    ]);
    $cashierDevice = Cashier::create(['name' => 'Device 1', 'branch_id' => $this->branch->id]);

    $cashierMan = CashierMan::create([
        'name' => 'Cashier Man 2',
        'user_name' => 'cashier_user_2',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $cashierDevice->id,
        'shift_id' => $shift->id,
    ]);
    $cashierToken = JWTAuth::fromUser($cashierMan);

    $delivery2 = Delivery::create([
        'name' => 'Captain Slow',
        'phone' => '01099999999',
        'branch_id' => $this->branch->id,
    ]);

    // Order 1: Delivery for driver 1, unpaid
    $order1 = Order::create([
        'module' => 'delivery',
        'status' => 'out_of_delivery',
        'branch_id' => $this->branch->id,
        'delivery_id' => $this->delivery->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 120,
        'final_price' => 120,
    ]);

    // Order 2: Delivery for driver 2, unpaid
    $order2 = Order::create([
        'module' => 'delivery',
        'status' => 'out_of_delivery',
        'branch_id' => $this->branch->id,
        'delivery_id' => $delivery2->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 200,
        'final_price' => 200,
    ]);

    // Order 3: Delivery for driver 1, but PAID (should not appear)
    $order3 = Order::create([
        'module' => 'delivery',
        'status' => 'delivered',
        'branch_id' => $this->branch->id,
        'delivery_id' => $this->delivery->id,
        'financial_account_id' => $this->financialAccountBank->id,
        'is_pos' => true,
        'total' => 300,
        'final_price' => 300,
    ]);

    // 1. Unfiltered request (returns both unpaid orders 1 & 2)
    $listRes = $this->withHeader('Authorization', 'Bearer '.$cashierToken)
        ->getJson('/api/cashier/delivery-orders/unpaid?per_page=10');

    $listRes->assertStatus(200)
        ->assertJsonPath('status', true);

    $ids = collect($listRes->json('data'))->pluck('id')->all();
    expect($ids)->toContain($order1->id)
        ->and($ids)->toContain($order2->id)
        ->and($ids)->not->toContain($order3->id);

    // 2. Filtered by delivery_id of driver 1
    $filteredRes = $this->withHeader('Authorization', 'Bearer '.$cashierToken)
        ->getJson("/api/cashier/delivery-orders/unpaid?delivery_id={$this->delivery->id}");

    $filteredRes->assertStatus(200);
    $filteredIds = collect($filteredRes->json('data'))->pluck('id')->all();
    expect($filteredIds)->toContain($order1->id)
        ->and($filteredIds)->not->toContain($order2->id);
});

test('cashier can settle payment for multiple delivery orders and updates paid, cashier_id, cashier_man_id, shift_id', function () {
    $shift = Shift::create([
        'name' => ['en' => 'Evening', 'ar' => 'Evening'],
        'start_time' => '16:00:00',
        'end_time' => '23:59:00',
        'branch_id' => $this->branch->id,
    ]);
    $cashierDevice = Cashier::create(['name' => 'Device 2', 'branch_id' => $this->branch->id]);

    $cashierMan = CashierMan::create([
        'name' => 'Cashier Man Settle',
        'user_name' => 'cashier_settle',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $cashierDevice->id,
        'shift_id' => $shift->id,
    ]);
    $cashierToken = JWTAuth::fromUser($cashierMan);

    $orderA = Order::create([
        'module' => 'delivery',
        'status' => 'out_of_delivery',
        'branch_id' => $this->branch->id,
        'delivery_id' => $this->delivery->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 150,
        'final_price' => 150,
    ]);

    $orderB = Order::create([
        'module' => 'delivery',
        'status' => 'delivered',
        'branch_id' => $this->branch->id,
        'delivery_id' => $this->delivery->id,
        'financial_account_id' => $this->financialAccountCash->id,
        'is_pos' => true,
        'total' => 250,
        'final_price' => 250,
    ]);

    expect($orderA->paid)->toBeFalse();
    expect($orderB->paid)->toBeFalse();

    // Settle both orders
    $settleRes = $this->withHeader('Authorization', 'Bearer '.$cashierToken)
        ->postJson('/api/cashier/delivery-orders/settle-payment', [
            'order_ids' => [$orderA->id, $orderB->id],
        ]);

    $settleRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.settled_count', 2)
        ->assertJsonPath('data.cashier_id', $cashierDevice->id)
        ->assertJsonPath('data.cashier_man_id', $cashierMan->id)
        ->assertJsonPath('data.shift_id', $shift->id);

    $freshA = $orderA->fresh();
    $freshB = $orderB->fresh();

    expect($freshA->paid)->toBeTrue()
        ->and($freshA->cashier_id)->toBe($cashierDevice->id)
        ->and($freshA->cashier_man_id)->toBe($cashierMan->id)
        ->and($freshA->shift_id)->toBe($shift->id);

    expect($freshB->paid)->toBeTrue()
        ->and($freshB->cashier_id)->toBe($cashierDevice->id)
        ->and($freshB->cashier_man_id)->toBe($cashierMan->id)
        ->and($freshB->shift_id)->toBe($shift->id);
});
