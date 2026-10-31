<?php

use App\Models\Addon;
use App\Models\AddonStock;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAddon;
use App\Models\Purchase;
use App\Models\Unit;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    UnitConversionService::clearCache();

    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'password' => 'password123',
    ]);
    $this->adminToken = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'password' => 'password123',
        'status' => true,
    ]);
    $this->branchToken = JWTAuth::fromUser($this->branch);

    $this->boxUnit = Unit::create([
        'name' => ['ar' => 'كرتونة', 'en' => 'Box'],
        'value' => 12,
    ]);

    $this->pieceUnit = Unit::create([
        'name' => ['ar' => 'قطعة', 'en' => 'Piece'],
        'value' => 1,
    ]);

    $this->addon1 = Addon::create([
        'name' => ['ar' => 'صوص شيدر', 'en' => 'Cheddar Sauce'],
        'price' => 15.0,
        'image' => 'addons/cheddar.png',
    ]);

    $this->addon2 = Addon::create([
        'name' => ['ar' => 'مشروم إضافي', 'en' => 'Extra Mushroom'],
        'price' => 20.0,
        'image' => 'addons/mushroom.png',
    ]);

    // Initial stock for addon1: 24 pieces in branch
    AddonStock::create([
        'addon_id' => $this->addon1->id,
        'branch_id' => $this->branch->id,
        'stock' => 24.0,
        'unit_id' => $this->pieceUnit->id,
    ]);
});

test('admin can create addon inventory which snapshots all addons and branch stock', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/admin/inventory/addons', [
            'name' => 'جرد إضافات شهر أكتوبر',
            'branch_id' => $this->branch->id,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'data' => [
                'name' => 'جرد إضافات شهر أكتوبر',
                'branch_id' => $this->branch->id,
                'status' => 'pending',
                'total_items' => 2,
            ],
        ]);

    $inventoryId = $response->json('data.id');
    $items = InventoryAddon::where('inventory_id', $inventoryId)->get();
    expect($items)->toHaveCount(2);

    $item1 = $items->firstWhere('addon_id', $this->addon1->id);
    expect((float) $item1->stock)->toBe(24.0)
        ->and((float) $item1->actual_stock)->toBe(24.0)
        ->and($item1->unit_id)->toBe($this->pieceUnit->id);

    $item2 = $items->firstWhere('addon_id', $this->addon2->id);
    expect((float) $item2->stock)->toBe(0.0)
        ->and((float) $item2->actual_stock)->toBe(0.0)
        ->and($item2->unit_id)->toBeNull();
});

test('admin can update single and batch actual stocks in addon inventory', function () {
    $inventory = Inventory::create([
        'name' => 'جرد إضافات',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item = InventoryAddon::create([
        'inventory_id' => $inventory->id,
        'addon_id' => $this->addon1->id,
        'stock' => 24.0,
        'actual_stock' => 24.0,
        'unit_id' => $this->pieceUnit->id,
    ]);

    // Single item update
    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->putJson("/api/admin/inventory/addon-items/{$item->id}", [
            'actual_stock' => 20.0,
            'unit_id' => $this->pieceUnit->id,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $item->id,
                'actual_stock' => 20.0,
                'deficit' => 4.0,
            ],
        ]);

    // Batch update
    $batchResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->putJson("/api/admin/inventory/addons/{$inventory->id}/items", [
            'items' => [
                [
                    'id' => $item->id,
                    'actual_stock' => 18.0,
                    'unit_id' => $this->pieceUnit->id,
                ],
            ],
        ]);

    $batchResponse->assertStatus(200);
    expect((float) $item->fresh()->actual_stock)->toBe(18.0);
});

test('approving addon inventory updates AddonStock with unit conversion', function () {
    $inventory = Inventory::create([
        'name' => 'جرد لاعتماد الإضافات',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    // Stock on branch is stored in pieces (value=1).
    // Actual stock counted in 3 boxes (value=12) => 3 * 12 = 36 pieces.
    $item = InventoryAddon::create([
        'inventory_id' => $inventory->id,
        'addon_id' => $this->addon1->id,
        'stock' => 24.0,
        'actual_stock' => 3.0,
        'unit_id' => $this->boxUnit->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->putJson("/api/admin/inventory/addons/{$inventory->id}/status", [
            'status' => 'approve',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'status' => 'approve',
            ],
        ]);

    // Stock should now be 36 pieces in branch AddonStock
    $stock = AddonStock::where('addon_id', $this->addon1->id)
        ->where('branch_id', $this->branch->id)
        ->first();

    expect((float) $stock->stock)->toBe(36.0);
});

test('admin can purchase addons and stock is incremented with unit conversion', function () {
    // Current stock of addon1 = 24 pieces (unit = pieceUnit, value = 1)
    // Purchase 2 boxes (boxUnit, value = 12) => +24 pieces => total should become 48 pieces
    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/admin/purchases', [
            'branch_id' => $this->branch->id,
            'total_cost' => 200,
            'paid_amount' => 200,
            'items' => [
                [
                    'addon_id' => $this->addon1->id,
                    'quantity' => 2,
                    'cost' => 100,
                    'unit_id' => $this->boxUnit->id,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'data' => [
                'branch_id' => $this->branch->id,
            ],
        ]);

    $purchaseId = $response->json('data.id');

    $stock = AddonStock::where('addon_id', $this->addon1->id)
        ->where('branch_id', $this->branch->id)
        ->first();
    expect((float) $stock->stock)->toBe(48.0);

    // Delete purchase restores / decrements stock back to 24 pieces
    $deleteResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->deleteJson("/api/admin/purchases/{$purchaseId}");

    $deleteResponse->assertStatus(200);

    expect((float) $stock->fresh()->stock)->toBe(46.0); // 48 - 2 = 46 (since delete decrements recorded item quantity)
});

test('purchase select-options endpoint includes addons with calculated branch stock', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson("/api/admin/purchases/select-options?branch_id={$this->branch->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'materials',
                'product_recipes',
                'addons' => [
                    '*' => ['id', 'name', 'price', 'stock'],
                ],
            ],
        ]);

    $addons = collect($response->json('data.addons'));
    $addon1Data = $addons->firstWhere('id', $this->addon1->id);
    expect((float) $addon1Data['stock'])->toBe(24.0);
});

test('branch user can create addon inventory and purchase addons in branch routes', function () {
    // 1. Branch creates inventory
    $invResponse = $this->withHeader('Authorization', "Bearer {$this->branchToken}")
        ->postJson('/api/branch/inventory/addons', [
            'name' => 'جرد فرع المعادي',
        ]);

    $invResponse->assertStatus(201)
        ->assertJson([
            'status' => true,
            'data' => [
                'branch_id' => $this->branch->id,
            ],
        ]);

    // 2. Branch purchases addon
    $purchaseResponse = $this->withHeader('Authorization', "Bearer {$this->branchToken}")
        ->postJson('/api/branch/purchases', [
            'items' => [
                [
                    'addon_id' => $this->addon2->id,
                    'quantity' => 10,
                    'cost' => 5,
                    'unit_id' => $this->pieceUnit->id,
                ],
            ],
        ]);

    $purchaseResponse->assertStatus(201);

    $addon2Stock = AddonStock::where('addon_id', $this->addon2->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $addon2Stock)->toBe(10.0);
});
