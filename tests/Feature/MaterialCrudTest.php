<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Material;
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

test('unauthenticated request to material endpoints returns 401', function () {
    $this->getJson('/api/admin/materials')->assertStatus(401);
    $this->getJson('/api/admin/materials/select-options')->assertStatus(401);
    $this->postJson('/api/admin/materials', [])->assertStatus(401);
});

test('admin can fetch select-options returning categories with id, name, and type', function () {
    $materialCategory = Category::create([
        'name' => ['ar' => 'خامات لحوم', 'en' => 'Meat Materials'],
        'image' => 'categories/meat.jpg',
        'type' => 'material',
        'status' => true,
    ]);

    $productCategory = Category::create([
        'name' => ['ar' => 'سندوتشات', 'en' => 'Sandwiches'],
        'image' => 'categories/sandwiches.jpg',
        'type' => 'product',
        'status' => true,
    ]);

    // Default returns material type categories
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/materials/select-options');

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
        ->and($response->json('data.categories.0.id'))->toBe($materialCategory->id);

    // Can also query all categories
    $allResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/materials/select-options?type=all');

    $allResponse->assertStatus(200);
    expect($allResponse->json('data.categories'))->toHaveCount(2);
});

test('admin can perform full CRUD on Material model with multilingual name', function () {
    $category = Category::create([
        'name' => ['ar' => 'خضروات', 'en' => 'Vegetables'],
        'image' => 'categories/vegetables.jpg',
        'type' => 'material',
    ]);

    // 1. Create (Store)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/materials', [
            'name' => [
                'ar' => 'طماطم طازجة',
                'en' => 'Fresh Tomatoes',
            ],
            'stock' => 100,
            'status' => true,
            'category_id' => $category->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'طماطم طازجة')
        ->assertJsonPath('data.name.en', 'Fresh Tomatoes')
        ->assertJsonPath('data.stock', 100)
        ->assertJsonPath('data.status', true)
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'name',
                'stock',
                'status',
                'category_id',
                'category',
                'created_at',
                'updated_at',
            ],
            'select_options' => ['categories'],
        ]);

    $materialId = $storeResponse->json('data.id');
    expect($materialId)->not->toBeNull();

    $this->assertDatabaseHas('materials', [
        'id' => $materialId,
        'stock' => 100,
        'status' => true,
        'category_id' => $category->id,
    ]);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/materials/{$materialId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $materialId)
        ->assertJsonPath('data.name.en', 'Fresh Tomatoes')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonStructure(['select_options' => ['categories']]);

    // 3. Update
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/materials/{$materialId}", [
            'name' => [
                'ar' => 'طماطم إيطالية ممتازة',
                'en' => 'Premium Italian Tomatoes',
            ],
            'stock' => 150,
            'status' => false,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'طماطم إيطالية ممتازة')
        ->assertJsonPath('data.name.en', 'Premium Italian Tomatoes')
        ->assertJsonPath('data.stock', 150)
        ->assertJsonPath('data.status', false);

    $this->assertDatabaseHas('materials', [
        'id' => $materialId,
        'stock' => 150,
        'status' => false,
    ]);

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/materials/{$materialId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('materials', ['id' => $materialId]);
});

test('admin can create Material using simple string name', function () {
    $category = Category::create([
        'name' => ['ar' => 'جبن وألبان', 'en' => 'Dairy & Cheese'],
        'image' => 'categories/dairy.jpg',
        'type' => 'material',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/materials', [
            'name' => 'Cheddar Cheese Block',
            'stock' => 30,
            'category_id' => $category->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Cheddar Cheese Block')
        ->assertJsonPath('data.name.ar', 'Cheddar Cheese Block')
        ->assertJsonPath('data.stock', 30);
});

test('admin can list materials with pagination and select_options', function () {
    Material::create([
        'name' => ['ar' => 'ملح طعام', 'en' => 'Table Salt'],
        'stock' => 50,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/materials');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'stock',
                    'status',
                    'category_id',
                    'category',
                ],
            ],
            'links',
            'meta',
            'select_options' => ['categories'],
        ]);
});
