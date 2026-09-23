<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        'name' => ['ar' => 'فرع رئيسي', 'en' => 'Main Branch'],
    ]);
});

test('unauthenticated request to purchase endpoints returns 401', function () {
    $this->getJson('/api/admin/purchases')->assertStatus(401);
    $this->getJson('/api/admin/purchases/select-options')->assertStatus(401);
    $this->postJson('/api/admin/purchases', [])->assertStatus(401);
});

test('admin can fetch select-options returning branches, materials, and product recipes', function () {
    $material = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'status' => true,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'عجينة بيتزا', 'en' => 'Pizza Dough'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 20,
    ]);

    // Test Arabic / default with branch_id
    $resAr = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/purchases/select-options?lang=ar&branch_id={$this->branch->id}");

    $resAr->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches',
                'materials' => [
                    '*' => ['id', 'name', 'stock'],
                ],
                'product_recipes' => [
                    '*' => ['id', 'name', 'stock'],
                ],
            ],
        ]);

    expect($resAr->json('data.materials.0.name'))->toBe('سكر')
        ->and((float) $resAr->json('data.materials.0.stock'))->toBe(50.0)
        ->and($resAr->json('data.product_recipes.0.name'))->toBe('عجينة بيتزا')
        ->and((float) $resAr->json('data.product_recipes.0.stock'))->toBe(20.0);

    // Test English
    $resEn = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/purchases/select-options?lang=en&branch_id={$this->branch->id}");

    $resEn->assertStatus(200);
    expect($resEn->json('data.materials.0.name'))->toBe('Sugar')
        ->and($resEn->json('data.product_recipes.0.name'))->toBe('Pizza Dough');
});

test('admin can create purchase with multiple items having individual quantity and cost', function () {
    $material = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'status' => true,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 10,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 5,
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'notes' => 'First purchase invoice',
        'items' => [
            [
                'material_id' => $material->id,
                'quantity' => 25,
                'cost' => 150.00,
            ],
            [
                'product_recipe_id' => $recipe->id,
                'quantity' => 10,
                'cost' => 80.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.total_cost', 230)
        ->assertJsonPath('data.total_quantity', 35)
        ->assertJsonPath('data.items.0.quantity', 25)
        ->assertJsonPath('data.items.0.cost', 150)
        ->assertJsonPath('data.items.1.quantity', 10)
        ->assertJsonPath('data.items.1.cost', 80);

    // Verify individual branch stock increments
    expect($material->stockForBranch($this->branch->id))->toBe(35.0)
        ->and($recipe->stockForBranch($this->branch->id))->toBe(15.0);

    $this->assertDatabaseHas('purchases', [
        'branch_id' => $this->branch->id,
        'total_cost' => 230,
        'total_quantity' => 35,
    ]);

    $this->assertDatabaseHas('purchase_items', [
        'material_id' => $material->id,
        'quantity' => 25,
        'cost' => 150.00,
    ]);
});

test('admin can create purchase with receipt image and item specifying both material and recipe', function () {
    Storage::fake('public');

    $material = Material::create([
        'name' => ['ar' => 'زيت', 'en' => 'Oil'],
        'status' => true,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'خلطة توابل', 'en' => 'Spice Mix'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 20,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 10,
    ]);

    $file = UploadedFile::fake()->image('invoice.jpg');

    $payload = [
        'branch_id' => $this->branch->id,
        'receipt' => $file,
        'items' => [
            [
                'material_id' => $material->id,
                'product_recipe_id' => $recipe->id,
                'quantity' => 8,
                'cost' => 120,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->post('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    expect($material->stockForBranch($this->branch->id))->toBe(28.0)
        ->and($recipe->stockForBranch($this->branch->id))->toBe(18.0);

    $purchase = Purchase::first();
    expect($purchase->receipt)->not->toBeNull();
    Storage::disk('public')->assertExists($purchase->receipt);
});

test('deleting a purchase restores (decrements) the stock of all its items in that branch', function () {
    $material = Material::create([
        'name' => ['ar' => 'أرز', 'en' => 'Rice'],
        'status' => true,
    ]);

    $matStock = MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 120,
    ]);

    $purchase = Purchase::create([
        'branch_id' => $this->branch->id,
        'total_cost' => 200,
        'total_quantity' => 20,
    ]);

    $purchase->items()->create([
        'material_id' => $material->id,
        'quantity' => 20,
        'cost' => 200,
    ]);

    expect($material->stockForBranch($this->branch->id))->toBe(120.0);

    // Delete the purchase
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/admin/purchases/'.$purchase->id);

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    // Stock must be restored back to 100
    expect($material->stockForBranch($this->branch->id))->toBe(100.0);

    $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    $this->assertDatabaseMissing('purchase_items', ['purchase_id' => $purchase->id]);
});

test('store validation fails if branch_id is missing or invalid', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', [
            'items' => [
                [
                    'material_id' => 1,
                    'quantity' => 10,
                    'cost' => 50,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['branch_id']);
});
