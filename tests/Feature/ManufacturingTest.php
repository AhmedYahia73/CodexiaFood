<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
        'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
    ]);

    $this->category = Category::create([
        'name' => ['en' => 'Burgers', 'ar' => 'برجر'],
        'image' => 'categories/burger.jpg',
        'type' => 'product',
    ]);

    $this->product = Product::create([
        'name' => ['en' => 'Cheeseburger', 'ar' => 'تشيز برجر'],
        'description' => ['en' => 'Delicious burger', 'ar' => 'برجر لذيذ'],
        'price' => 75.00,
        'image' => 'products/burger.jpg',
        'category_id' => $this->category->id,
    ]);

    $this->beefPattyRecipe = ProductRecipe::create([
        'name' => ['en' => 'Beef Patty', 'ar' => 'شريحة اللحم'],
        'category_id' => $this->category->id,
    ]);

    $this->beefMaterial = Material::create([
        'name' => ['en' => 'Minced Beef', 'ar' => 'لحم مفروم'],
        'category_id' => $this->category->id,
    ]);

    $this->bunMaterial = Material::create([
        'name' => ['en' => 'Burger Bun', 'ar' => 'خبز البرجر'],
        'category_id' => $this->category->id,
    ]);

    // Seed branch stock
    MaterialStock::create([
        'material_id' => $this->beefMaterial->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    MaterialStock::create([
        'material_id' => $this->bunMaterial->id,
        'branch_id' => $this->branch->id,
        'stock' => 20,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $this->beefPattyRecipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 10,
    ]);
});

test('products table does not have stock column because it is made on demand', function () {
    expect(Schema::hasColumn('products', 'stock'))->toBeFalse();
    expect(Schema::hasColumn('materials', 'stock'))->toBeFalse();
    expect(Schema::hasColumn('product_recipes', 'stock'))->toBeFalse();
});

test('product_manufacturings table does not have count column', function () {
    expect(Schema::hasColumn('product_manufacturings', 'count'))->toBeFalse();
});

test('admin can fetch select options for product-manufacturings', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/product-manufacturings/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'data' => ['products', 'product_recipes', 'materials'],
        ]);

    expect($response->json('data.products'))->not->toBeEmpty();
    expect($response->json('data.product_recipes'))->not->toBeEmpty();
    expect($response->json('data.materials'))->not->toBeEmpty();
});

test('admin can perform full CRUD on ProductManufacturing specification', function () {
    // 1. Create specification for Cheeseburger (uses Beef Patty recipe & Burger Bun material)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/product-manufacturings', [
            'product_id' => $this->product->id,
            'recipes' => [
                [
                    'product_recipe_id' => $this->beefPattyRecipe->id,
                    'count' => 1,
                ],
                [
                    'material_id' => $this->bunMaterial->id,
                    'count' => 1,
                ],
            ],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.product_id', $this->product->id)
        ->assertJsonCount(2, 'data.recipes');

    $specId = $storeResponse->json('data.id');

    // 2. Read specification
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/product-manufacturings/{$specId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $specId)
        ->assertJsonPath('data.product.id', $this->product->id);

    // 3. Update specification
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/product-manufacturings/{$specId}", [
            'recipes' => [
                [
                    'material_id' => $this->bunMaterial->id,
                    'count' => 2,
                ],
            ],
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data.recipes')
        ->assertJsonPath('data.recipes.0.count', 2);

    // 4. Delete specification
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/product-manufacturings/{$specId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('product_manufacturings', ['id' => $specId]);
});

test('admin can fetch specification with available stock for manufacturing by branch', function () {
    $spec = ProductManufacturing::create(['product_id' => $this->product->id]);
    $spec->productRecipeManufacturings()->create([
        'material_id' => $this->bunMaterial->id,
        'count' => 2,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/manufacturing/specifications?product_id={$this->product->id}&branch_id={$this->branch->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.product_id', $this->product->id)
        ->assertJsonPath('data.recipes.0.material.stock', 20);
});

test('manufacturing fails when branch ingredient stock is insufficient', function () {
    // Bun has stock: 20 in branch. Request to consume 30.
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'count' => 5,
            'recipes' => [
                [
                    'material_id' => $this->bunMaterial->id,
                    'count' => 30, // exceeds available stock 20
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);

    // Verify branch stock did not change
    expect($this->bunMaterial->stockForBranch($this->branch->id))->toBe(20.0);
});

test('manufacturing succeeds and accurately updates branch stocks and creates history', function () {
    // Initial: Bun stock = 20, Patty stock = 10
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'count' => 5, // produce 5 cheeseburgers
            'recipes' => [
                [
                    'material_id' => $this->bunMaterial->id,
                    'count' => 5, // consume 5 buns
                ],
                [
                    'product_recipe_id' => $this->beefPattyRecipe->id,
                    'count' => 5, // consume 5 patties
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.count', 5)
        ->assertJsonPath('data.product_id', $this->product->id);

    // Check stocks after manufacturing:
    // Bun stock should decrease by 5 (20 - 5 = 15)
    expect($this->bunMaterial->stockForBranch($this->branch->id))->toBe(15.0);
    // Patty stock should decrease by 5 (10 - 5 = 5)
    expect($this->beefPattyRecipe->stockForBranch($this->branch->id))->toBe(5.0);

    // Verify manufacturing list history
    $listId = $response->json('data.id');
    $this->assertDatabaseHas('manufacturing_lists', [
        'id' => $listId,
        'branch_id' => $this->branch->id,
        'product_id' => $this->product->id,
        'count' => 5,
    ]);

    $this->assertDatabaseHas('manufacturing_recipes', [
        'manufacturing_list_id' => $listId,
        'material_id' => $this->bunMaterial->id,
        'count' => 5,
    ]);

    // Check show endpoint
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/manufacturing/{$listId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $listId)
        ->assertJsonPath('data.branch_id', $this->branch->id);
});

test('manufacturing a product recipe increments recipe branch stock and decrements raw materials in branch', function () {
    // Initial: Beef Material stock = 50, Patty recipe stock = 10
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'branch_id' => $this->branch->id,
            'product_recipe_id' => $this->beefPattyRecipe->id,
            'count' => 8, // produce 8 patties
            'recipes' => [
                [
                    'material_id' => $this->beefMaterial->id,
                    'count' => 16, // consume 16 units of minced beef
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.product_recipe_id', $this->beefPattyRecipe->id)
        ->assertJsonPath('data.count', 8);

    // Beef Patty recipe stock should increase by 8 (10 + 8 = 18)
    expect($this->beefPattyRecipe->stockForBranch($this->branch->id))->toBe(18.0);
    // Minced beef material stock should decrease by 16 (50 - 16 = 34)
    expect($this->beefMaterial->stockForBranch($this->branch->id))->toBe(34.0);
});

test('manufacturing fails if branch_id is missing', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'product_id' => $this->product->id,
            'count' => 5,
            'recipes' => [
                [
                    'material_id' => $this->bunMaterial->id,
                    'count' => 2,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['branch_id']);
});
