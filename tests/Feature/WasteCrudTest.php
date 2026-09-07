<?php

use App\Models\Admin;
use App\Models\Material;
use App\Models\ProductRecipe;
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
});

test('unauthenticated request to waste endpoints returns 401', function () {
    $this->getJson('/api/admin/wastes')->assertStatus(401);
    $this->getJson('/api/admin/wastes/select-options')->assertStatus(401);
    $this->postJson('/api/admin/wastes', [])->assertStatus(401);
});

test('admin can fetch select-options returning materials and product recipes with id, name, and stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'stock' => 50,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'عجينة بيتزا', 'en' => 'Pizza Dough'],
        'stock' => 20,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/wastes/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
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
        ->and((int) $response->json('data.materials.0.stock'))->toBe(50)
        ->and($response->json('data.product_recipes'))->toHaveCount(1)
        ->and($response->json('data.product_recipes.0.id'))->toBe($recipe->id)
        ->and((int) $response->json('data.product_recipes.0.stock'))->toBe(20);
});

test('admin can create waste for material and stock is decremented', function () {
    $material = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'stock' => 100,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'material_id' => $material->id,
            'count' => 15,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.material_id', $material->id)
        ->assertJsonPath('data.count', 15)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'product_recipe_id', 'material_id', 'count', 'created_at', 'updated_at'],
            'select_options' => ['materials', 'product_recipes'],
        ]);

    $wasteId = $response->json('data.id');
    $this->assertDatabaseHas('wastes', [
        'id' => $wasteId,
        'material_id' => $material->id,
        'count' => 15,
    ]);

    expect((int) $material->fresh()->stock)->toBe(85);
});

test('admin can create waste for product recipe and stock is decremented', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'stock' => 40,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'product_recipe_id' => $recipe->id,
            'count' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.product_recipe_id', $recipe->id)
        ->assertJsonPath('data.count', 10);

    expect((int) $recipe->fresh()->stock)->toBe(30);
});

test('creating waste fails with 422 if count exceeds available stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'زيت', 'en' => 'Oil'],
        'stock' => 5,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'material_id' => $material->id,
            'count' => 10,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);

    expect((int) $material->fresh()->stock)->toBe(5);
    $this->assertDatabaseEmpty('wastes');
});

test('creating waste fails with 422 if neither or both items are provided', function () {
    $material = Material::create([
        'name' => 'Salt',
        'stock' => 50,
    ]);
    $recipe = ProductRecipe::create([
        'name' => 'Base Sauce',
        'stock' => 50,
    ]);

    // Neither
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'count' => 5,
        ])
        ->assertStatus(422);

    // Both
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'material_id' => $material->id,
            'product_recipe_id' => $recipe->id,
            'count' => 5,
        ])
        ->assertStatus(422);
});

test('admin can update only count: increasing count decrements stock by the difference', function () {
    $material = Material::create([
        'name' => ['ar' => 'طماطم', 'en' => 'Tomato'],
        'stock' => 100,
    ]);

    // Initial waste of 20 -> stock becomes 80
    $waste = Waste::create([
        'material_id' => $material->id,
        'count' => 20,
    ]);
    $material->decrement('stock', 20);
    expect((int) $material->fresh()->stock)->toBe(80);

    // Update count to 25 (+5 diff)
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/wastes/{$waste->id}", [
            'count' => 25,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.count', 25);

    expect((int) $material->fresh()->stock)->toBe(75)
        ->and((int) $waste->fresh()->count)->toBe(25);
});

test('updating count fails with 422 if positive difference exceeds available stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'جبنة موزاريللا', 'en' => 'Mozzarella'],
        'stock' => 3, // only 3 left in stock
    ]);

    $waste = Waste::create([
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

    expect((int) $material->fresh()->stock)->toBe(3)
        ->and((int) $waste->fresh()->count)->toBe(10);
});

test('admin can update only count: decreasing count restores stock by the difference', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'شوكولاتة سائلة', 'en' => 'Liquid Chocolate'],
        'stock' => 50,
    ]);

    // Initial waste of 20
    $waste = Waste::create([
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

    expect((int) $recipe->fresh()->stock)->toBe(58)
        ->and((int) $waste->fresh()->count)->toBe(12);
});

test('deleting waste restores the entire count back to stock', function () {
    $material = Material::create([
        'name' => ['ar' => 'بصل', 'en' => 'Onion'],
        'stock' => 70,
    ]);

    $waste = Waste::create([
        'material_id' => $material->id,
        'count' => 30,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/wastes/{$waste->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    // Stock should be restored by 30 (70 + 30 = 100)
    expect((int) $material->fresh()->stock)->toBe(100);
    $this->assertDatabaseMissing('wastes', ['id' => $waste->id]);
});

test('deleting waste for product recipe restores the entire count back to stock', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'عصير برتقال مركز', 'en' => 'Orange Concentrate'],
        'stock' => 25,
    ]);

    $waste = Waste::create([
        'product_recipe_id' => $recipe->id,
        'count' => 15,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/wastes/{$waste->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    expect((int) $recipe->fresh()->stock)->toBe(40);
    $this->assertDatabaseMissing('wastes', ['id' => $waste->id]);
});

test('admin can view and list wastes with relationships and select options', function () {
    $material = Material::create([
        'name' => ['ar' => 'مشروم', 'en' => 'Mushroom'],
        'stock' => 20,
    ]);

    $waste = Waste::create([
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
            'data' => ['id', 'product_recipe_id', 'material_id', 'count', 'material'],
            'select_options' => ['materials', 'product_recipes'],
        ]);

    // Index
    $indexResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/wastes');

    $indexResponse->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'count', 'material'],
            ],
            'links',
            'meta',
            'select_options' => ['materials', 'product_recipes'],
        ]);
});
