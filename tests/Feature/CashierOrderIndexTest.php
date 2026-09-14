<?php

use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Order;
use App\Models\StartShift;
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
});

test('cashier can list orders and filter by module', function () {
    $takeawayOrder = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'name' => 'Mahmoud Ali',
        'phone' => '01011112222',
        'is_pos' => true,
        'total' => 100,
        'final_price' => 100,
    ]);

    $dineinOrder = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'dinein',
        'name' => 'Youssef Hassan',
        'phone' => '01122223333',
        'is_pos' => true,
        'total' => 200,
        'final_price' => 200,
    ]);

    // Query takeaway only
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?module=takeaway');

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['id'])->toBe($takeawayOrder->id);
    expect($data[0]['module'])->toBe('takeaway');

    // Query dinein only
    $responseDinein = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?module=dinein');

    $responseDinein->assertOk();
    $dineinData = $responseDinein->json('data');
    expect(count($dineinData))->toBe(1);
    expect($dineinData[0]['id'])->toBe($dineinOrder->id);
    expect($dineinData[0]['module'])->toBe('dinein');
});

test('module validation rejects invalid module enum', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?module=invalid_module');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['module']);
});

test('cashier can filter orders by search with order id or number', function () {
    $order1 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'name' => 'Customer A',
        'phone' => '05000000001',
        'is_pos' => true,
        'total' => 50,
        'final_price' => 50,
    ]);

    $order2 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'name' => 'Customer B',
        'phone' => '05000000002',
        'is_pos' => true,
        'total' => 70,
        'final_price' => 70,
    ]);

    // Search numeric ID
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/cashier/orders?search={$order1->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['id'])->toBe($order1->id);

    // Search with hash symbol like #ID
    $responseHash = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/cashier/orders?search=%23{$order2->id}");

    $responseHash->assertOk();
    $dataHash = $responseHash->json('data');
    expect(count($dataHash))->toBe(1);
    expect($dataHash[0]['id'])->toBe($order2->id);

    // Search using order_number parameter
    $responseOrderNum = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/cashier/orders?order_number={$order1->id}");

    $responseOrderNum->assertOk();
    $dataOrderNum = $responseOrderNum->json('data');
    expect(count($dataOrderNum))->toBe(1);
    expect($dataOrderNum[0]['id'])->toBe($order1->id);
});

test('cashier can filter orders by search with customer name or phone', function () {
    $order1 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'delivery',
        'name' => 'Tarek Mostafa',
        'phone' => '01099887766',
        'is_pos' => true,
        'total' => 120,
        'final_price' => 120,
    ]);

    $order2 = Order::create([
        'shift_id' => $this->shift->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'delivery',
        'name' => 'Ahmed Samir',
        'phone' => '01555555555',
        'is_pos' => true,
        'total' => 80,
        'final_price' => 80,
    ]);

    // Search by name
    $responseName = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?search=Tarek');

    $responseName->assertOk();
    $dataName = $responseName->json('data');
    expect(count($dataName))->toBe(1);
    expect($dataName[0]['name'])->toBe('Tarek Mostafa');

    // Search by phone
    $responsePhone = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?search=0155555');

    $responsePhone->assertOk();
    $dataPhone = $responsePhone->json('data');
    expect(count($dataPhone))->toBe(1);
    expect($dataPhone[0]['phone'])->toBe('01555555555');

    // Direct phone filter
    $responseDirectPhone = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?phone=0109988');

    $responseDirectPhone->assertOk();
    $dataDirectPhone = $responseDirectPhone->json('data');
    expect(count($dataDirectPhone))->toBe(1);
    expect($dataDirectPhone[0]['phone'])->toBe('01099887766');

    // Direct name filter
    $responseDirectName = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/cashier/orders?name=Ahmed');

    $responseDirectName->assertOk();
    $dataDirectName = $responseDirectName->json('data');
    expect(count($dataDirectName))->toBe(1);
    expect($dataDirectName[0]['name'])->toBe('Ahmed Samir');

    // Direct id filter
    $responseDirectId = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/cashier/orders?id={$order1->id}");

    $responseDirectId->assertOk();
    $dataDirectId = $responseDirectId->json('data');
    expect(count($dataDirectId))->toBe(1);
    expect($dataDirectId[0]['id'])->toBe($order1->id);
});
