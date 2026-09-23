<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryProductRecipe;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
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
        'status' => true,
    ]);

    $this->recipe1 = ProductRecipe::create([
        'name' => ['ar' => 'صلصة ثوم', 'en' => 'Garlic Sauce'],
        'status' => true,
    ]);

    $this->recipe2 = ProductRecipe::create([
        'name' => ['ar' => 'خلطة شاورما', 'en' => 'Shawarma Mix'],
        'status' => true,
    ]);

    // Initial stock: recipe1 has 15 in branch, recipe2 has NO stock record (0)
    ProductRecipeStock::create([
        'product_recipe_id' => $this->recipe1->id,
        'branch_id' => $this->branch->id,
        'stock' => 15.0,
    ]);
});

test('admin can create recipe inventory which snapshots all recipes and branch stock', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/admin/inventory/recipes', [
        'name' => 'جرد وصفات نهاية الأسبوع',
        'branch_id' => $this->branch->id,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'data' => [
                'name' => 'جرد وصفات نهاية الأسبوع',
                'branch_id' => $this->branch->id,
                'status' => 'pending',
            ],
        ]);

    $inventoryId = $response->json('data.id');

    // Both recipes should be populated
    $items = InventoryProductRecipe::where('inventory_id', $inventoryId)->get();
    expect($items)->toHaveCount(2);

    $item1 = $items->firstWhere('product_recipe_id', $this->recipe1->id);
    expect((float) $item1->stock)->toBe(15.0)
        ->and((float) $item1->actual_stock)->toBe(15.0);

    $item2 = $items->firstWhere('product_recipe_id', $this->recipe2->id);
    expect((float) $item2->stock)->toBe(0.0)
        ->and((float) $item2->actual_stock)->toBe(0.0);
});

test('admin can list pending recipe inventories with date and branch_name', function () {
    $inventory = Inventory::create([
        'name' => 'جرد معلق',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 10,
        'actual_stock' => 10,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson('/api/admin/inventory/recipes/pending');

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

test('admin can list completed recipe inventories where status != pending', function () {
    $approvedInv = Inventory::create([
        'name' => 'جرد معتمد',
        'branch_id' => $this->branch->id,
        'status' => 'approve',
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $approvedInv->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 10,
        'actual_stock' => 10,
    ]);

    $pendingInv = Inventory::create([
        'name' => 'جرد قيد الانتظار',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $pendingInv->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 10,
        'actual_stock' => 10,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson('/api/admin/inventory/recipes/history');

    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($approvedInv->id)
        ->and($ids)->not->toContain($pendingInv->id);
});

test('admin can view recipe inventory details with item actual stock and differences', function () {
    $inventory = Inventory::create([
        'name' => 'تفاصيل الجرد',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item = InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 15,
        'actual_stock' => 12,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson("/api/admin/inventory/recipes/{$inventory->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $inventory->id,
                'name' => 'تفاصيل الجرد',
                'items' => [
                    [
                        'id' => $item->id,
                        'product_recipe_id' => $this->recipe1->id,
                        'stock' => 15,
                        'actual_stock' => 12,
                        'deficit' => 3,
                        'shortage' => 3,
                        'difference' => -3,
                    ],
                ],
            ],
        ]);
});

test('admin can update actual stock of an inventory recipe item', function () {
    $inventory = Inventory::create([
        'name' => 'جرد للتعديل',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item = InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 15,
        'actual_stock' => 15,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/recipe-items/{$item->id}", [
        'actual_stock' => 11.5,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
        ]);

    $item->refresh();
    expect((float) $item->actual_stock)->toBe(11.5);
});

test('admin can batch update actual stocks for inventory recipe items', function () {
    $inventory = Inventory::create([
        'name' => 'جرد تعديل جماعي',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    $item1 = InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 15,
        'actual_stock' => 15,
    ]);

    $item2 = InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe2->id,
        'stock' => 0,
        'actual_stock' => 0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/recipes/{$inventory->id}/items", [
        'items' => [
            ['id' => $item1->id, 'actual_stock' => 14],
            ['id' => $item2->id, 'actual_stock' => 8],
        ],
    ]);

    $response->assertStatus(200);

    expect((float) $item1->fresh()->actual_stock)->toBe(14.0)
        ->and((float) $item2->fresh()->actual_stock)->toBe(8.0);
});

test('approving recipe inventory overwrites ProductRecipeStock with actual_stock', function () {
    $inventory = Inventory::create([
        'name' => 'جرد للاعتماد',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 15,
        'actual_stock' => 18,
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe2->id,
        'stock' => 0,
        'actual_stock' => 7,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/recipes/{$inventory->id}/status", [
        'status' => 'approve',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'status' => 'approve',
            ],
        ]);

    expect((float) ProductRecipeStock::where('product_recipe_id', $this->recipe1->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(18.0)
        ->and((float) ProductRecipeStock::where('product_recipe_id', $this->recipe2->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(7.0);
});

test('rejecting recipe inventory changes status without modifying stock', function () {
    $inventory = Inventory::create([
        'name' => 'جرد للرفض',
        'branch_id' => $this->branch->id,
        'status' => 'pending',
    ]);

    InventoryProductRecipe::create([
        'inventory_id' => $inventory->id,
        'product_recipe_id' => $this->recipe1->id,
        'stock' => 15,
        'actual_stock' => 2,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->putJson("/api/admin/inventory/recipes/{$inventory->id}/status", [
        'status' => 'reject',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'status' => 'reject',
            ],
        ]);

    // Stock must remain 15, untouched
    expect((float) ProductRecipeStock::where('product_recipe_id', $this->recipe1->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(15.0);
});
