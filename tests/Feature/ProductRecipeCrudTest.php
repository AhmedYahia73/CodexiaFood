<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\ProductRecipe;
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

test('unauthenticated request to product-recipe endpoints returns 401', function () {
    $this->getJson('/api/admin/product-recipes')->assertStatus(401);
    $this->getJson('/api/admin/product-recipes/select-options')->assertStatus(401);
    $this->postJson('/api/admin/product-recipes', [])->assertStatus(401);
});

test('admin can fetch select-options returning categories with id, name, and type', function () {
    $recipeCategory = Category::create([
        'name' => ['ar' => 'وصفات العجائن', 'en' => 'Dough Recipes'],
        'image' => 'categories/dough.jpg',
        'type' => 'recipe',
        'status' => true,
    ]);

    $productCategory = Category::create([
        'name' => ['ar' => 'وجبات رئيسية', 'en' => 'Main Meals'],
        'image' => 'categories/meals.jpg',
        'type' => 'product',
        'status' => true,
    ]);

    // Default returns recipe type categories
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/product-recipes/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'categories' => [
                    '*' => ['id', 'name', 'type'],
                ],
            ],
        ]);

    expect($response->json('data.categories'))->toHaveCount(1)
        ->and($response->json('data.categories.0.id'))->toBe($recipeCategory->id);

    // Can also query all categories
    $allResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/product-recipes/select-options?type=all');

    $allResponse->assertStatus(200);
    expect($allResponse->json('data.categories'))->toHaveCount(2);
});

test('admin can perform full CRUD on ProductRecipe model with multilingual name', function () {
    $category = Category::create([
        'name' => ['ar' => 'وصفات الصوصات', 'en' => 'Sauce Recipes'],
        'image' => 'categories/sauce.jpg',
        'type' => 'recipe',
    ]);

    // 1. Create (Store)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/product-recipes', [
            'name' => [
                'ar' => 'صوص الباربيكيو المدخن',
                'en' => 'Smoked BBQ Sauce',
            ],
            'status' => true,
            'stock' => 50,
            'category_id' => $category->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'صوص الباربيكيو المدخن')
        ->assertJsonPath('data.name.en', 'Smoked BBQ Sauce')
        ->assertJsonPath('data.status', true)
        ->assertJsonPath('data.stock', 50)
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'name',
                'status',
                'stock',
                'category_id',
                'category',
                'created_at',
                'updated_at',
            ],
            'select_options' => ['categories'],
        ]);

    $recipeId = $storeResponse->json('data.id');
    expect($recipeId)->not->toBeNull();

    $this->assertDatabaseHas('product_recipes', [
        'id' => $recipeId,
        'stock' => 50,
        'status' => true,
        'category_id' => $category->id,
    ]);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/product-recipes/{$recipeId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $recipeId)
        ->assertJsonPath('data.name.en', 'Smoked BBQ Sauce')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonStructure(['select_options' => ['categories']]);

    // 3. Update
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/product-recipes/{$recipeId}", [
            'name' => [
                'ar' => 'صوص الباربيكيو الحار',
                'en' => 'Spicy BBQ Sauce',
            ],
            'stock' => 80,
            'status' => false,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'صوص الباربيكيو الحار')
        ->assertJsonPath('data.name.en', 'Spicy BBQ Sauce')
        ->assertJsonPath('data.stock', 80)
        ->assertJsonPath('data.status', false);

    $this->assertDatabaseHas('product_recipes', [
        'id' => $recipeId,
        'stock' => 80,
        'status' => false,
    ]);

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/product-recipes/{$recipeId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('product_recipes', ['id' => $recipeId]);
});

test('admin can create ProductRecipe using simple string name', function () {
    $category = Category::create([
        'name' => ['ar' => 'خلطات التتبيل', 'en' => 'Marinade Mixes'],
        'image' => 'categories/marinades.jpg',
        'type' => 'recipe',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/product-recipes', [
            'name' => 'Garlic Herb Butter',
            'stock' => 25,
            'category_id' => $category->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Garlic Herb Butter')
        ->assertJsonPath('data.name.ar', 'Garlic Herb Butter')
        ->assertJsonPath('data.stock', 25);
});

test('admin can list product recipes with pagination and select_options', function () {
    ProductRecipe::create([
        'name' => ['ar' => 'وصفة تجريبية', 'en' => 'Test Recipe'],
        'stock' => 15,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/product-recipes');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'stock',
                    'category_id',
                    'category',
                ],
            ],
            'links',
            'meta',
            'select_options' => ['categories'],
        ]);
});
