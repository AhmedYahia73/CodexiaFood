<?php

use App\Http\Resources\HallTableResource;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    $this->admin = Admin::create([
        'name' => 'Admin User',
        'password' => 'password123',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);
});

test('hall table automatically generates uuid for code upon creation', function () {
    $table = HallTable::create([
        'name' => 'T1',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    expect($table->code)->not->toBeNull();
    expect(Str::isUuid($table->code))->toBeTrue();
});

test('hall table retains custom code if explicitly provided', function () {
    $customUuid = (string) Str::uuid();

    $table = HallTable::create([
        'name' => 'T2',
        'code' => $customUuid,
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    expect($table->code)->toBe($customUuid);
});

test('hall table resource includes code attribute', function () {
    $table = HallTable::create([
        'name' => 'T3',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    $resource = (new HallTableResource($table))->toArray(request());

    expect($resource)->toHaveKey('code');
    expect($resource['code'])->toBe($table->code);
    expect(Str::isUuid($resource['code']))->toBeTrue();
});

test('table info can be resolved by uuid code in route', function () {
    $table = HallTable::create([
        'name' => 'T4',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    $this->getJson('/api/table/'.$table->code)
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $table->id,
                'table_code' => $table->code,
                'code' => $table->code,
                'name' => 'T4',
            ],
        ]);
});

test('table info can be resolved by table_code query parameter', function () {
    $table = HallTable::create([
        'name' => 'T5',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    $this->getJson('/api/tableOrder?table_code='.$table->code)
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $table->id,
                'table_code' => $table->code,
                'name' => 'T5',
            ],
        ]);
});

test('cart store, index, and clear work seamlessly using table_code', function () {
    $product = Product::create([
        'name' => ['ar' => 'برجر دجاج', 'en' => 'Chicken Burger'],
        'image' => 'products/chicken.jpg',
        'price' => 100.00,
    ]);

    $table = HallTable::create([
        'name' => 'T6',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    // 1. Store cart item by table_code
    $storeRes = $this->postJson('/api/table/cart', [
        'table_code' => $table->code,
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertStatus(201)
        ->assertJson([
            'status' => true,
            'table_code' => $table->code,
        ]);

    expect($storeRes->json('data.table_code'))->toBe($table->code);

    // 2. Index cart by table_code
    $indexRes = $this->getJson('/api/table/cart?table_code='.$table->code)
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'table_code' => $table->code,
        ]);

    expect($indexRes->json('data'))->toHaveCount(1);
    expect($indexRes->json('data.0.quantity'))->toBe(2);

    // 3. Clear cart by table_code
    $this->deleteJson('/api/table/cart/clear', [
        'table_code' => $table->code,
    ])->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'تم تفريغ السلة بنجاح',
        ]);

    // 4. Verify cart is empty for this table
    $emptyRes = $this->getJson('/api/table/cart?table_code='.$table->code)
        ->assertStatus(200);

    expect($emptyRes->json('data'))->toHaveCount(0);
});
