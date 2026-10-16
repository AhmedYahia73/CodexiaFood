<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Saler;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'password' => 'password123',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
    ]);

    $this->supplier = Supplier::create([
        'name' => 'شركة الأغذية',
        'phone' => '01000000001',
        'balance' => 0.00,
    ]);

    $this->saler = Saler::create([
        'name' => 'شركة التوزيع',
        'phone' => '01000000002',
        'balance' => 0.00,
    ]);

    $this->material = Material::create([
        'name' => ['ar' => 'دقيق فاخر', 'en' => 'Premium Flour'],
        'status' => true,
    ]);

    $this->recipe = ProductRecipe::create([
        'name' => ['ar' => 'خلطة صوص', 'en' => 'Sauce Mix'],
        'status' => true,
    ]);
});

test('purchasing quantity with unit cost correctly increments branch stock and updates supplier balance with remaining amount', function () {
    // Initial stock is 0
    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(0.0);

    // Buy 10 units at 50 LE unit cost = 500 LE total cost. Pay 100 LE now, remaining 400 LE.
    $payload = [
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'paid_amount' => 100.00,
        'due_date' => '2026-11-20',
        'items' => [
            [
                'material_id' => $this->material->id,
                'quantity' => 10,
                'cost' => 50.00, // Unit cost 50 LE
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 500)
        ->assertJsonPath('data.paid_amount', 100)
        ->assertJsonPath('data.remaining_amount', 400)
        ->assertJsonPath('data.dues.0.amount', 400);

    // Stock should be 10.0
    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(10.0);

    // Supplier balance must be incremented by 400 LE remaining amount
    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(400.00);

    $purchaseId = $response->json('data.id');

    // Deleting the purchase must restore stock back to 0 and reverse supplier balance back to 0
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/purchases/{$purchaseId}")
        ->assertStatus(200);

    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(0.0);

    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(0.00);
});

test('selling items with unit price correctly deducts branch stock and increments saler balance with remaining amount', function () {
    // Seed branch stock: 50 units
    MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    // Sell 15 units at 60 LE unit price = 900 LE total. Pay 200 LE now, remaining 700 LE.
    $payload = [
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'paid_amount' => 200.00,
        'due_date' => '2026-12-01',
        'items' => [
            [
                'material_id' => $this->material->id,
                'quantity' => 15,
                'cost' => 60.00, // Unit price 60 LE
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 900)
        ->assertJsonPath('data.paid_amount', 200)
        ->assertJsonPath('data.remaining_amount', 700)
        ->assertJsonPath('data.dues.0.amount', 700);

    // Stock should be 50 - 15 = 35.0
    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(35.0);

    // Saler balance must be incremented by 700 LE remaining amount
    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe(700.00);

    $saleId = $response->json('data.id');

    // Deleting the sale must restore stock back to 50 and reverse saler balance back to 0
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/sales/{$saleId}")
        ->assertStatus(200);

    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(50.0);

    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe(0.00);
});
