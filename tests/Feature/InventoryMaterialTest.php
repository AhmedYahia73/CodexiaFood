<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMaterial;
use App\Models\Material;
use App\Models\MaterialStock;
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
        'name' => ['ar' => 'فرع النزهة', 'en' => 'Nozha Branch'],
        'status' => true,
    ]);

    $this->material1 = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'status' => true,
    ]);

    $this->material2 = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'status' => true,
    ]);

    // Initial stock: material1 has 50 in branch, material2 has NO stock record (0)
    MaterialStock::create([
        'material_id' => $this->material1->id,
        'branch_id' => $this->branch->id,
        'stock' => 50.0,
    ]);
});

test('admin can create material inventory which snapshots all materials and branch stock', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/admin/inventory/materials', [
        'name' => 'جرد خامات شهري',
        'branch_id' => $this->branch->id,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'data' => [
                'name' => 'جرد خامات شهري',
                'branch_id' => $this->branch->id,
                'status' => 'pending',
            ],
        ]);

    $inventoryId = $response->json('data.id');

    // Both materials should be populated
    $items = InventoryMaterial::where('inventory_id', $inventoryId)->get();
    expect($items)->toHaveCount(2);

    $item1 = $items->firstWhere('material_id', $this->material1->id);
    expect((float) $item1->stock)->toBe(50.0)
        ->and((float) $item1->actual_stock)->toBe(50.0);

    $item2 = $items->firstWhere('material_id', $this->material2->id);
    expect((float) $item2->stock)->toBe(0.0)
        ->and((float) $item2->actual_stock)->toBe(0.0);
});

test('admin can list pending material inventories with date and branch_name', function () {
    $inventory = Inventory::create([
        'name' => 'جرد خامات معلق',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 20,
        'actual_stock' => 20,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson('/api/admin/inventory/materials/pending');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'branch_id',
                    'branch_name',
                    'status',
                    'created_at',
                    'date',
                ],
            ],
        ]);

    expect($response->json('data.0.status'))->toBe('pending')
        ->and($response->json('data.0.id'))->toBe($inventory->id);
});

test('admin can list completed material inventories where status != pending', function () {
    $approvedInv = Inventory::create([
        'name' => 'جرد خامات معتمد',
        'branch_id' => $this->branch->id,
        'status' => 'approve',
    ]);

    InventoryMaterial::create([
        'inventory_id' => $approvedInv->id,
        'material_id' => $this->material1->id,
        'stock' => 20,
        'actual_stock' => 20,
    ]);

    $pendingInv = Inventory::create([
        'name' => 'جرد قيد الانتظار',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryMaterial::create([
        'inventory_id' => $pendingInv->id,
        'material_id' => $this->material1->id,
        'stock' => 20,
        'actual_stock' => 20,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson('/api/admin/inventory/materials/history');

    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($approvedInv->id)
        ->and($ids)->not->toContain($pendingInv->id);
});

test('admin can view material inventory details with items', function () {
    $inventory = Inventory::create([
        'name' => 'تفاصيل جرد الخامات',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item = InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 50,
        'actual_stock' => 45,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson("/api/admin/inventory/materials/{$inventory->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $inventory->id,
                'name' => 'تفاصيل جرد الخامات',
                'items' => [
                    [
                        'id' => $item->id,
                        'material_id' => $this->material1->id,
                        'stock' => 50,
                        'actual_stock' => 45,
                        'deficit' => 5,
                        'shortage' => 5,
                        'difference' => -5,
                    ],
                ],
            ],
        ]);
});

test('admin can update actual stock of an inventory material item', function () {
    $inventory = Inventory::create([
        'name' => 'جرد للتعديل',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item = InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 50,
        'actual_stock' => 50,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/material-items/{$item->id}", [
        'actual_stock' => 42.5,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
        ]);

    $item->refresh();
    expect((float) $item->actual_stock)->toBe(42.5);
});

test('admin can batch update actual stocks for inventory material items', function () {
    $inventory = Inventory::create([
        'name' => 'جرد تعديل جماعي للخامات',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item1 = InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 50,
        'actual_stock' => 50,
    ]);

    $item2 = InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material2->id,
        'stock' => 0,
        'actual_stock' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/materials/{$inventory->id}/items", [
        'items' => [
            ['id' => $item1->id, 'actual_stock' => 48],
            ['id' => $item2->id, 'actual_stock' => 25],
        ],
    ]);

    $response->assertStatus(200);

    expect((float) $item1->fresh()->actual_stock)->toBe(48.0)
        ->and((float) $item2->fresh()->actual_stock)->toBe(25.0);
});

test('approving material inventory overwrites MaterialStock with actual_stock', function () {
    $inventory = Inventory::create([
        'name' => 'جرد خامات للاعتماد',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 50,
        'actual_stock' => 55,
    ]);

    InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material2->id,
        'stock' => 0,
        'actual_stock' => 30,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/materials/{$inventory->id}/status", [
        'status' => 'approve',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'status' => 'approve',
            ],
        ]);

    expect((float) MaterialStock::where('material_id', $this->material1->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(55.0)
        ->and((float) MaterialStock::where('material_id', $this->material2->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(30.0);
});

test('rejecting material inventory changes status without modifying stock', function () {
    $inventory = Inventory::create([
        'name' => 'جرد خامات للرفض',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryMaterial::create([
        'inventory_id' => $inventory->id,
        'material_id' => $this->material1->id,
        'stock' => 50,
        'actual_stock' => 10,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/materials/{$inventory->id}/status", [
        'status' => 'reject',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'status' => 'reject',
            ],
        ]);

    // Stock remains 50, untouched
    expect((float) MaterialStock::where('material_id', $this->material1->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(50.0);
});
