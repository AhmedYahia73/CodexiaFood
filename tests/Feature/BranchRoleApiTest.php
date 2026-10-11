<?php

use App\Models\Branch;
use App\Models\Cashier;
use App\Models\Hall;
use App\Models\Kitchen;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch1 = Branch::create([
        'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        'user_name' => 'branch1_user',
        'password' => 'secret123',
    ]);

    $this->branch2 = Branch::create([
        'name' => ['en' => 'Secondary Branch', 'ar' => 'الفرع الثاني'],
        'user_name' => 'branch2_user',
        'password' => 'secret123',
    ]);

    $this->token1 = JWTAuth::fromUser($this->branch1);
    $this->token2 = JWTAuth::fromUser($this->branch2);
});

test('unauthenticated request to branch endpoints returns 401', function () {
    $response = $this->getJson('/api/branch/cashiers');

    $response->assertStatus(401);
});

test('branch can login with guard branch and receives token with role branch', function () {
    $response = $this->postJson('/api/auth/login', [
        'name' => 'branch1_user',
        'password' => 'secret123',
        'guard' => 'branch',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.guard', 'branch')
        ->assertJsonStructure(['data' => ['access_token', 'user']]);
});

test('branch index only returns records belonging to authenticated branch', function () {
    Cashier::create(['name' => 'Cashier 1', 'branch_id' => $this->branch1->id]);
    Cashier::create(['name' => 'Cashier 2', 'branch_id' => $this->branch2->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->getJson('/api/branch/cashiers');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Cashier 1');
});

test('branch store automatically assigns branch_id to authenticated branch', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/cashiers', [
            'name' => 'New Cashier For Branch 1',
            'branch_id' => $this->branch2->id, // Attempt to set another branch
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'New Cashier For Branch 1')
        ->assertJsonPath('data.branch.id', $this->branch1->id);

    $this->assertDatabaseHas('cashiers', [
        'name' => 'New Cashier For Branch 1',
        'branch_id' => $this->branch1->id,
    ]);
});

test('branch cannot view or modify or delete records belonging to another branch', function () {
    $foreignCashier = Cashier::create(['name' => 'Foreign Cashier', 'branch_id' => $this->branch2->id]);

    // Show
    $showRes = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->getJson("/api/branch/cashiers/{$foreignCashier->id}");
    $showRes->assertStatus(403);

    // Update
    $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->putJson("/api/branch/cashiers/{$foreignCashier->id}", [
            'name' => 'Hacked Name',
        ]);
    $updateRes->assertStatus(403);

    // Delete
    $deleteRes = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->deleteJson("/api/branch/cashiers/{$foreignCashier->id}");
    $deleteRes->assertStatus(403);

    // Ensure database remained intact
    $this->assertDatabaseHas('cashiers', [
        'id' => $foreignCashier->id,
        'name' => 'Foreign Cashier',
    ]);
});

test('branch dashboard statistics only aggregates orders of the authenticated branch', function () {
    Order::create([
        'branch_id' => $this->branch1->id,
        'module' => 'takeaway',
        'total' => 100,
        'final_price' => 100,
        'created_at' => now(),
    ]);

    Order::create([
        'branch_id' => $this->branch2->id,
        'module' => 'takeaway',
        'total' => 500,
        'final_price' => 500,
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->getJson('/api/branch/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_orders', 1)
        ->assertJsonPath('data.total_final_price', 100);
});

test('branch order creation automatically assigns branch_id', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/orders', [
            'module' => 'takeaway',
            'total' => 150,
            'final_price' => 150,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch.id', $this->branch1->id);

    $this->assertDatabaseHas('orders', [
        'branch_id' => $this->branch1->id,
        'final_price' => 150,
    ]);
});

test('branch inventory materials initializes stock from authenticated branch stock', function () {
    $mat = Material::create([
        'name' => ['en' => 'Tomato', 'ar' => 'طماطم'],
        'unit' => 'kg',
    ]);

    MaterialStock::create([
        'material_id' => $mat->id,
        'branch_id' => $this->branch1->id,
        'stock' => 50,
    ]);

    MaterialStock::create([
        'material_id' => $mat->id,
        'branch_id' => $this->branch2->id,
        'stock' => 200,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/inventory/materials', [
            'name' => 'Stock Audit Oct 2026',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.branch_id', $this->branch1->id)
        ->assertJsonPath('data.items.0.stock', 50);
});

test('branch waste recording checks and decrements stock in authenticated branch only', function () {
    $mat = Material::create([
        'name' => ['en' => 'Cheese', 'ar' => 'جبنة'],
        'unit' => 'kg',
    ]);

    MaterialStock::create([
        'material_id' => $mat->id,
        'branch_id' => $this->branch1->id,
        'stock' => 10,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/wastes', [
            'material_id' => $mat->id,
            'count' => 3,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('material_stocks', [
        'material_id' => $mat->id,
        'branch_id' => $this->branch1->id,
        'stock' => 7,
    ]);
});

test('branch hall and table management isolates branches', function () {
    $hall1 = Hall::create([
        'name' => ['en' => 'Main Hall', 'ar' => 'الصالة الرئيسية'],
        'branch_id' => $this->branch1->id,
    ]);

    $hall2 = Hall::create([
        'name' => ['en' => 'Second Hall', 'ar' => 'صالة 2'],
        'branch_id' => $this->branch2->id,
    ]);

    // Branch 1 cannot create a table in Hall 2
    $responseFail = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/hall-tables', [
            'name' => 'Table 1',
            'hall_id' => $hall2->id,
            'status' => true,
            'base_url' => 'https://example.com',
        ]);
    $responseFail->assertStatus(422);

    // Branch 1 can create table in Hall 1
    $responseOk = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/hall-tables', [
            'name' => 'Table 1',
            'hall_id' => $hall1->id,
            'status' => true,
            'base_url' => 'https://example.com',
        ]);
    $responseOk->assertStatus(201)
        ->assertJsonPath('data.branch.id', $this->branch1->id);
});

test('branch purchase increments stock in authenticated branch only', function () {
    $mat = Material::create([
        'name' => ['en' => 'Flour', 'ar' => 'دقيق'],
        'unit' => 'kg',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/purchases', [
            'items' => [
                [
                    'material_id' => $mat->id,
                    'quantity' => 10,
                    'cost' => 50,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('material_stocks', [
        'material_id' => $mat->id,
        'branch_id' => $this->branch1->id,
        'stock' => 10,
    ]);

    // Ensure branch 2 stock is not created or affected
    $this->assertDatabaseMissing('material_stocks', [
        'material_id' => $mat->id,
        'branch_id' => $this->branch2->id,
    ]);
});

test('branch kitchen assignment rejects kitchens belonging to other branches', function () {
    $kitchen2 = Kitchen::create([
        'name' => ['en' => 'Kitchen 2', 'ar' => 'مطبخ 2'],
        'password' => 'secret123',
        'branch_id' => $this->branch2->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token1)
        ->postJson('/api/branch/kitchen-products/assign', [
            'kitchen_id' => $kitchen2->id,
            'product_ids' => [],
        ]);

    $response->assertStatus(422);
});
