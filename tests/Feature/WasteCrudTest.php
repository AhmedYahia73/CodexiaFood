<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Waste;
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
    ]);
});

test('unauthenticated request to waste endpoints returns 401', function () {
    $this->getJson('/api/admin/wastes')->assertStatus(401);
    $this->getJson('/api/admin/wastes/select-options')->assertStatus(401);
    $this->postJson('/api/admin/wastes', [])->assertStatus(401);
});

test('admin can fetch select-options returning branches, materials, and product recipes with stock', function () {
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

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/wastes/select-options?branch_id={$this->branch->id}");

    $response->assertStatus(200)
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

    expect($response->json('data.materials'))->toHaveCount(1)
        ->and($response->json('data.materials.0.id'))->toBe($material->id)
        ->and((float) $response->json('data.materials.0.stock'))->toBe(50.0)
        ->and($response->json('data.product_recipes'))->toHaveCount(1)
        ->and($response->json('data.product_recipes.0.id'))->toBe($recipe->id)
        ->and((float) $response->json('data.product_recipes.0.stock'))->toBe(20.0);
});

test('admin can create waste for material and branch stock is decremented', function () {
    $material = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 100,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branch->id,
            'material_id' => $material->id,
            'count' => 15,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.material_id', $material->id)
        ->assertJsonPath('data.count', 15)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'branch_id', 'product_recipe_id', 'material_id', 'count', 'created_at', 'updated_at'],
            'select_options' => ['branches', 'materials', 'product_recipes'],
        ]);

    $wasteId = $response->json('data.id');
    $this->assertDatabaseHas('wastes', [
        'id' => $wasteId,
        'branch_id' => $this->branch->id,
        'material_id' => $material->id,
        'count' => 15,
    ]);

    expect($material->stockForBranch($this->branch->id))->toBe(85.0);
});

test('admin can create waste for product recipe and branch stock is decremented', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'status' => true,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 40,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branch->id,
            'product_recipe_id' => $recipe->id,
            'count' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.product_recipe_id', $recipe->id)
        ->assertJsonPath('data.count', 10);

    expect($recipe->stockForBranch($this->branch->id))->toBe(30.0);
});

test('creating waste fails with 422 if count exceeds branch available stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'زيت', 'en' => 'Oil'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 5,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branch->id,
            'material_id' => $material->id,
            'count' => 10,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);

    expect($material->stockForBranch($this->branch->id))->toBe(5.0);
    $this->assertDatabaseEmpty('wastes');
});

test('creating waste fails with 422 if neither or both items are provided or branch_id missing', function () {
    $material = Material::create([
        'name' => ['ar' => 'ملح', 'en' => 'Salt'],
        'status' => true,
    ]);
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة', 'en' => 'Sauce'],
        'status' => true,
    ]);

    // Missing branch_id
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'material_id' => $material->id,
            'count' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['branch_id']);

    // Neither material nor recipe
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branch->id,
            'count' => 5,
        ])
        ->assertStatus(422);

    // Both material and recipe
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branch->id,
            'material_id' => $material->id,
            'product_recipe_id' => $recipe->id,
            'count' => 5,
        ])
        ->assertStatus(422);
});

test('admin can update only count: increasing count decrements stock by the difference', function () {
    $material = Material::create([
        'name' => ['ar' => 'طماطم', 'en' => 'Tomato'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 80,
    ]);

    // Initial waste of 20
    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'material_id' => $material->id,
        'count' => 20,
    ]);

    // Update count to 25 (+5 diff)
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/wastes/{$waste->id}", [
            'count' => 25,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.count', 25);

    expect($material->stockForBranch($this->branch->id))->toBe(75.0)
        ->and((int) $waste->fresh()->count)->toBe(25);
});

test('updating count fails with 422 if positive difference exceeds branch available stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'جبنة موزاريللا', 'en' => 'Mozzarella'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 3, // only 3 left in stock
    ]);

    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'material_id' => $material->id,
        'count' => 10,
    ]);

    // Attempting to increase count from 10 to 15 (diff = +5, but stock is only 3)
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/wastes/{$waste->id}", [
            'count' => 15,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);

    expect($material->stockForBranch($this->branch->id))->toBe(3.0)
        ->and((int) $waste->fresh()->count)->toBe(10);
});

test('admin can update only count: decreasing count restores branch stock by the difference', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'شوكولاتة سائلة', 'en' => 'Liquid Chocolate'],
        'status' => true,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    // Initial waste of 20
    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'product_recipe_id' => $recipe->id,
        'count' => 20,
    ]);

    // Update count down to 12 (diff = -8, so 8 restored to stock)
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/wastes/{$waste->id}", [
            'count' => 12,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.count', 12);

    expect($recipe->stockForBranch($this->branch->id))->toBe(58.0)
        ->and((int) $waste->fresh()->count)->toBe(12);
});

test('deleting waste restores the entire count back to branch stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'بصل', 'en' => 'Onion'],
        'status' => true,
    ]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 70,
    ]);

    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'material_id' => $material->id,
        'count' => 30,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/wastes/{$waste->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    // Stock should be restored by 30 (70 + 30 = 100)
    expect($material->stockForBranch($this->branch->id))->toBe(100.0);
    $this->assertDatabaseMissing('wastes', ['id' => $waste->id]);
});

test('deleting waste for product recipe restores the entire count back to branch stock', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'عصير برتقال مركز', 'en' => 'Orange Concentrate'],
        'status' => true,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 25,
    ]);

    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'product_recipe_id' => $recipe->id,
        'count' => 15,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/wastes/{$waste->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    expect($recipe->stockForBranch($this->branch->id))->toBe(40.0);
    $this->assertDatabaseMissing('wastes', ['id' => $waste->id]);
});

test('admin can view and list wastes with relationships and select options', function () {
    $material = Material::create([
        'name' => ['ar' => 'مشروم', 'en' => 'Mushroom'],
        'status' => true,
    ]);

    $waste = Waste::create([
        'branch_id' => $this->branch->id,
        'material_id' => $material->id,
        'count' => 5,
    ]);

    // Show
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/wastes/{$waste->id}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $waste->id)
        ->assertJsonPath('data.material.id', $material->id)
        ->assertJsonStructure([
            'status',
            'data' => ['id', 'branch_id', 'product_recipe_id', 'material_id', 'count', 'material'],
            'select_options' => ['branches', 'materials', 'product_recipes'],
        ]);

    // Index
    $indexResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/wastes');

    $indexResponse->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'branch_id', 'count', 'material'],
            ],
            'links',
            'meta',
            'select_options' => ['branches', 'materials', 'product_recipes'],
        ]);
});
