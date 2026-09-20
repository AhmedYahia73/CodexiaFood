<?php

use App\Models\Admin;
use App\Models\Material;
use App\Models\ProductRecipe;
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
});

test('unauthenticated request to purchase endpoints returns 401', function () {
    $this->getJson('/api/admin/purchases')->assertStatus(401);
    $this->getJson('/api/admin/purchases/select-options')->assertStatus(401);
    $this->postJson('/api/admin/purchases', [])->assertStatus(401);
});

test('admin can fetch purchase select-options with localized names by lang query', function () {
    $material = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'stock' => 50,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'عجينة بيتزا', 'en' => 'Pizza Dough'],
        'stock' => 20,
    ]);

    // Test Arabic
    $resAr = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchases/select-options?lang=ar');

    $resAr->assertStatus(200)
        ->assertJsonPath('status', true);

    expect($resAr->json('data.materials.0.name'))->toBe('سكر')
        ->and($resAr->json('data.materials.0.stock'))->toBe(50)
        ->and($resAr->json('data.product_recipes.0.name'))->toBe('عجينة بيتزا')
        ->and($resAr->json('data.product_recipes.0.stock'))->toBe(20);

    // Test English
    $resEn = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchases/select-options?lang=en');

    $resEn->assertStatus(200);
    expect($resEn->json('data.materials.0.name'))->toBe('Sugar')
        ->and($resEn->json('data.product_recipes.0.name'))->toBe('Pizza Dough');
});

test('admin can create single purchase for material and stock is incremented', function () {
    $material = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'stock' => 10,
    ]);

    $payload = [
        'material_id' => $material->id,
        'quantity' => 25,
        'cost' => 150.50,
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.quantity', 25)
        ->assertJsonPath('data.cost', 150.5);

    expect($material->fresh()->stock)->toBe(35);
    $this->assertDatabaseHas('purchases', [
        'quantity' => 25,
        'cost' => 150.50,
    ]);
});

test('admin can create single purchase for product recipe and stock is incremented', function () {
    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'stock' => 5,
    ]);

    $payload = [
        'product_recipe_id' => $recipe->id,
        'quantity' => 15,
        'cost' => 80,
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    expect($recipe->fresh()->stock)->toBe(20);
});

test('admin can create single purchase with both material and product recipe and both stocks increment', function () {
    $material = Material::create([
        'name' => ['ar' => 'زيت', 'en' => 'Oil'],
        'stock' => 30,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صوص برجر', 'en' => 'Burger Sauce'],
        'stock' => 12,
    ]);

    $payload = [
        'material_ids' => [$material->id],
        'product_recipe_id' => [$recipe->id],
        'quantity' => 10,
        'cost' => 300,
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    expect($material->fresh()->stock)->toBe(40)
        ->and($recipe->fresh()->stock)->toBe(22);
});

test('admin can create multi-row purchases with receipt image and stocks are incremented', function () {
    Storage::fake('public');

    $material1 = Material::create([
        'name' => ['ar' => 'أرز', 'en' => 'Rice'],
        'stock' => 100,
    ]);

    $material2 = Material::create([
        'name' => ['ar' => 'ملح', 'en' => 'Salt'],
        'stock' => 50,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'خلطة توابل', 'en' => 'Spice Mix'],
        'stock' => 15,
    ]);

    $file = UploadedFile::fake()->image('receipt.jpg');

    $payload = [
        'receipt' => $file,
        'items' => [
            [
                'material_id' => $material1->id,
                'quantity' => 20,
                'cost' => 100,
            ],
            [
                'material_ids' => [$material2->id],
                'product_recipe_id' => [$recipe->id],
                'quantity' => 10,
                'cost' => 85,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->post('/api/admin/purchases', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    expect($material1->fresh()->stock)->toBe(120)
        ->and($material2->fresh()->stock)->toBe(60)
        ->and($recipe->fresh()->stock)->toBe(25);

    $purchases = Purchase::all();
    expect($purchases)->toHaveCount(2);
    expect($purchases[0]->receipt)->not->toBeNull();
    Storage::disk('public')->assertExists($purchases[0]->receipt);
});

test('store validation fails if neither material nor recipe is provided', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', [
            'quantity' => 10,
            'cost' => 100,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);
});

test('store validation fails if quantity is zero or negative', function () {
    $material = Material::create([
        'name' => ['ar' => 'سكر', 'en' => 'Sugar'],
        'stock' => 50,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', [
            'material_id' => $material->id,
            'quantity' => 0,
            'cost' => 50,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);
});

test('admin can list purchases and view single purchase', function () {
    $material = Material::create([
        'name' => ['ar' => 'طماطم', 'en' => 'Tomato'],
        'stock' => 10,
    ]);

    $purchase = Purchase::create([
        'material_ids' => [$material->id],
        'quantity' => 5,
        'cost' => 25,
    ]);

    // Test Index
    $indexRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchases');

    $indexRes->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'quantity', 'cost', 'materials'],
            ],
            'select_options',
        ]);

    // Test Show
    $showRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchases/'.$purchase->id);

    $showRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $purchase->id)
        ->assertJsonPath('data.quantity', 5)
        ->assertJsonPath('data.cost', 25);
});

test('admin can delete purchase', function () {
    $purchase = Purchase::create([
        'quantity' => 5,
        'cost' => 25,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/admin/purchases/'.$purchase->id);

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('purchases', [
        'id' => $purchase->id,
    ]);
});
