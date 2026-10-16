<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Material;
use App\Models\Purchase;
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
        'name' => ['ar' => 'فرع النصر', 'en' => 'Nasr Branch'],
    ]);

    $this->supplier = Supplier::create([
        'name' => 'مورد المواد الغذائية',
        'phone' => '01011112222',
        'balance' => 1000.00,
    ]);

    $this->material = Material::create([
        'name' => ['ar' => 'زيت طعام', 'en' => 'Cooking Oil'],
        'status' => true,
    ]);
});

test('creating purchase with partial paid_amount creates purchase_due and increments supplier balance', function () {
    $payload = [
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 1000.00,
        'paid_amount' => 400.00,
        'due_date' => '2026-11-15',
        'notes' => 'شراء زيت مع دفع مقدم',
        'items' => [
            [
                'material_id' => $this->material->id,
                'quantity' => 10,
                'cost' => 1000.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 1000)
        ->assertJsonPath('data.paid_amount', 400)
        ->assertJsonPath('data.remaining_amount', 600)
        ->assertJsonPath('data.dues.0.amount', 600)
        ->assertJsonPath('data.dues.0.date', '2026-11-15');

    $purchaseId = $response->json('data.id');

    // Supplier balance should have increased by remaining_amount (1000 initial + 600 remaining = 1600)
    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(1600.00);

    $this->assertDatabaseHas('purchase_dues', [
        'purchase_id' => $purchaseId,
        'amount' => 600.00,
    ]);

    // Deleting purchase reverses the supplier balance
    $deleteRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/purchases/{$purchaseId}");

    $deleteRes->assertStatus(200);

    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(1000.00);

    $this->assertDatabaseMissing('purchase_dues', [
        'purchase_id' => $purchaseId,
    ]);
});

test('creating purchase with full payment does not create dues and does not change supplier balance', function () {
    $payload = [
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 500.00,
        'paid_amount' => 500.00,
        'items' => [
            [
                'material_id' => $this->material->id,
                'quantity' => 5,
                'cost' => 500.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.total_cost', 500)
        ->assertJsonPath('data.paid_amount', 500)
        ->assertJsonPath('data.remaining_amount', 0);

    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(1000.00);

    $purchaseId = $response->json('data.id');
    $this->assertDatabaseMissing('purchase_dues', [
        'purchase_id' => $purchaseId,
    ]);
});

test('creating purchase with multiple installment dues creates multiple purchase_dues records', function () {
    $payload = [
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 1000.00,
        'paid_amount' => 400.00,
        'notes' => 'شراء على 3 أقساط',
        'dues' => [
            ['amount' => 200.00, 'date' => '2026-11-01', 'notes' => 'القسط الأول'],
            ['amount' => 200.00, 'date' => '2026-12-01', 'notes' => 'القسط الثاني'],
            ['amount' => 200.00, 'date' => '2027-01-01', 'notes' => 'القسط الثالث'],
        ],
        'items' => [
            [
                'material_id' => $this->material->id,
                'quantity' => 10,
                'cost' => 1000.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 1000)
        ->assertJsonPath('data.paid_amount', 400)
        ->assertJsonPath('data.remaining_amount', 600)
        ->assertJsonCount(3, 'data.dues');

    $purchaseId = $response->json('data.id');

    // Supplier balance incremented by remaining_amount (1000 + 600 = 1600)
    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe(1600.00);

    $this->assertDatabaseCount('purchase_dues', 3);
    $this->assertDatabaseHas('purchase_dues', [
        'purchase_id' => $purchaseId,
        'amount' => 200.00,
        'notes' => 'القسط الأول',
    ]);
    $this->assertDatabaseHas('purchase_dues', [
        'purchase_id' => $purchaseId,
        'amount' => 200.00,
        'notes' => 'القسط الثاني',
    ]);
    $this->assertDatabaseHas('purchase_dues', [
        'purchase_id' => $purchaseId,
        'amount' => 200.00,
        'notes' => 'القسط الثالث',
    ]);

    $purchase = Purchase::with('dues')->find($purchaseId);
    expect($purchase->dues[0]->date->format('Y-m-d'))->toBe('2026-11-01')
        ->and($purchase->dues[1]->date->format('Y-m-d'))->toBe('2026-12-01')
        ->and($purchase->dues[2]->date->format('Y-m-d'))->toBe('2027-01-01');
});
