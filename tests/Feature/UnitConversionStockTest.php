<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMaterial;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
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

    $this->token = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع رئيسي', 'en' => 'Main Branch'],
    ]);

    $this->kgUnit = Unit::create([
        'name' => ['ar' => 'كيلوجرام', 'en' => 'Kilogram'],
        'value' => 1000,
    ]);

    $this->gramUnit = Unit::create([
        'name' => ['ar' => 'جرام', 'en' => 'Gram'],
        'value' => 1,
    ]);

    $this->pieceUnit = Unit::create([
        'name' => ['ar' => 'قطعة', 'en' => 'Piece'],
        'value' => 1,
    ]);
});

test('UnitConversionService mathematically converts between units accurately', function () {
    // 2 kg to grams = 2 * (1000 / 1) = 2000
    $grams = UnitConversionService::convert(2.0, $this->kgUnit->id, $this->gramUnit->id);
    expect($grams)->toBe(2000.0);

    // 500 grams to kg = 500 * (1 / 1000) = 0.5
    $kg = UnitConversionService::convert(500.0, $this->gramUnit->id, $this->kgUnit->id);
    expect($kg)->toBe(0.5);

    // Same unit conversion returns identical value
    $same = UnitConversionService::convert(15.0, $this->kgUnit->id, $this->kgUnit->id);
    expect($same)->toBe(15.0);

    // Null units fallback gracefully
    $fallback = UnitConversionService::convert(10.0, null, $this->kgUnit->id);
    expect($fallback)->toBe(10.0);
});

test('manufacturing converts recipe ingredients from recipe unit to stock unit correctly', function () {
    $category = Category::create([
        'name' => ['ar' => 'مكونات', 'en' => 'Ingredients'],
        'image' => 'categories/default.png',
    ]);

    $sugar = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'category_id' => $category->id,
    ]);

    // MaterialStock is stored in KG (10 kg)
    MaterialStock::create([
        'material_id' => $sugar->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
        'unit_id' => $this->kgUnit->id,
    ]);

    $dough = ProductRecipe::create([
        'name' => ['ar' => 'عجينة', 'en' => 'Dough'],
        'category_id' => $category->id,
    ]);

    // Execute manufacturing of 2 units of Dough, each requiring 500 Grams of Sugar
    // Total sugar required = 1000 Grams = 1 KG.
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'branch_id' => $this->branch->id,
            'product_recipe_id' => $dough->id,
            'count' => 2,
            'unit_id' => $this->pieceUnit->id,
            'recipes' => [
                [
                    'material_id' => $sugar->id,
                    'count' => 1000, // 1000 grams
                    'unit_id' => $this->gramUnit->id,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    // Sugar stock should be decremented from 10 kg by 1 kg => 9 kg!
    $updatedStock = MaterialStock::where('material_id', $sugar->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $updatedStock)->toBe(9.0);

    // Target recipe stock should be incremented by 2
    $recipeStock = ProductRecipeStock::where('product_recipe_id', $dough->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $recipeStock)->toBe(2.0);
});

test('selling finished product converts BOM ingredients from recipe unit to stock unit', function () {
    $category = Category::create([
        'name' => ['ar' => 'حلويات', 'en' => 'Sweets'],
        'image' => 'categories/default.png',
    ]);

    $flour = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'category_id' => $category->id,
    ]);

    // Flour stock stored in KG (5 kg)
    MaterialStock::create([
        'material_id' => $flour->id,
        'branch_id' => $this->branch->id,
        'stock' => 5.0,
        'unit_id' => $this->kgUnit->id,
    ]);

    $cake = Product::create([
        'name' => ['ar' => 'كيك', 'en' => 'Cake'],
        'price' => 50.00,
        'image' => 'products/default.png',
        'category_id' => $category->id,
    ]);

    // Specification: 1 Cake needs 250 Grams of Flour
    $spec = ProductManufacturing::create([
        'product_id' => $cake->id,
    ]);

    $spec->productRecipeManufacturings()->create([
        'material_id' => $flour->id,
        'count' => 250, // 250 grams
        'unit_id' => $this->gramUnit->id,
    ]);

    // Sell 2 Cakes => Requires 500 Grams of Flour = 0.5 KG
    $saleResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $cake->id,
                    'quantity' => 2,
                    'cost' => 50,
                ],
            ],
        ]);

    $saleResponse->assertStatus(201)
        ->assertJsonPath('status', true);

    // Flour stock should be 5.0 - 0.5 = 4.5 KG
    $updatedFlourStock = MaterialStock::where('material_id', $flour->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $updatedFlourStock)->toBe(4.5);

    // Destroy/Refund sale => Should restore 0.5 KG back to 5.0 KG
    $saleId = $saleResponse->json('data.id');
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/sales/{$saleId}");

    $deleteResponse->assertStatus(200);

    $restoredStock = MaterialStock::where('material_id', $flour->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $restoredStock)->toBe(5.0);
});

test('material inventory tracks unit_id and converts actual stock on approval', function () {
    $category = Category::create([
        'name' => ['ar' => 'خامات', 'en' => 'Raw'],
        'image' => 'categories/default.png',
    ]);

    $coffee = Material::create([
        'name' => ['ar' => 'بن', 'en' => 'Coffee'],
        'category_id' => $category->id,
    ]);

    MaterialStock::create([
        'material_id' => $coffee->id,
        'branch_id' => $this->branch->id,
        'stock' => 2.0, // 2 kg
        'unit_id' => $this->kgUnit->id,
    ]);

    // Create inventory
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/inventory/materials', [
            'name' => 'Monthly Coffee Inventory',
            'branch_id' => $this->branch->id,
        ]);

    $storeResponse->assertStatus(201);
    $inventoryId = $storeResponse->json('data.id');

    $inventoryItem = InventoryMaterial::where('inventory_id', $inventoryId)->first();
    expect($inventoryItem->unit_id)->toBe($this->kgUnit->id);

    // Update actual stock counted in grams: 3500 grams (= 3.5 kg)
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/inventory/material-items/{$inventoryItem->id}", [
            'actual_stock' => 3500,
            'unit_id' => $this->gramUnit->id,
        ])->assertStatus(200);

    // Approve inventory
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/inventory/materials/{$inventoryId}/status", [
            'status' => 'approve',
        ])->assertStatus(200);

    // Stock in MaterialStock should now be converted to 3.5 KG
    $finalStock = MaterialStock::where('material_id', $coffee->id)
        ->where('branch_id', $this->branch->id)
        ->value('stock');

    expect((float) $finalStock)->toBe(3.5);
});
