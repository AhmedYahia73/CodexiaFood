<?php

use App\Http\Resources\OrderResource;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\FinancialAccount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
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
