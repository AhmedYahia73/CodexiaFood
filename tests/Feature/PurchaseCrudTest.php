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

test('admin can create purchase with multiple items having individual quantity and cost', function () {
    $material = Material::create([
        'name' => ['ar' => 'دقيق', 'en' => 'Flour'],
        'stock' => 10,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'stock' => 5,
    ]);

    $payload = [
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
        ->assertJsonPath('data.total_cost', 230)
        ->assertJsonPath('data.total_quantity', 35)
        ->assertJsonPath('data.items.0.quantity', 25)
        ->assertJsonPath('data.items.0.cost', 150)
        ->assertJsonPath('data.items.1.quantity', 10)
        ->assertJsonPath('data.items.1.cost', 80);

    // Verify individual stock increments
    expect($material->fresh()->stock)->toBe(35)
        ->and($recipe->fresh()->stock)->toBe(15);

    $this->assertDatabaseHas('purchases', [
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
        'stock' => 20,
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'خلطة توابل', 'en' => 'Spice Mix'],
        'stock' => 10,
    ]);

    $file = UploadedFile::fake()->image('invoice.jpg');

    $payload = [
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

    expect($material->fresh()->stock)->toBe(28)
        ->and($recipe->fresh()->stock)->toBe(18);

    $purchase = Purchase::first();
    expect($purchase->receipt)->not->toBeNull();
    Storage::disk('public')->assertExists($purchase->receipt);
});

test('deleting a purchase restores (decrements) the stock of all its items', function () {
    $material = Material::create([
        'name' => ['ar' => 'أرز', 'en' => 'Rice'],
        'stock' => 100,
    ]);

    $purchase = Purchase::create([
        'total_cost' => 200,
        'total_quantity' => 20,
    ]);

    $purchase->items()->create([
        'material_id' => $material->id,
        'quantity' => 20,
        'cost' => 200,
    ]);

    // Suppose stock was incremented to 120 upon purchase
    $material->increment('stock', 20);
    expect($material->fresh()->stock)->toBe(120);

    // Delete the purchase
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/admin/purchases/'.$purchase->id);

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    // Stock must be restored back to 100
    expect($material->fresh()->stock)->toBe(100);

    $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    $this->assertDatabaseMissing('purchase_items', ['purchase_id' => $purchase->id]);
});

test('store validation fails if an item specifies neither material nor recipe', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', [
            'items' => [
                [
                    'quantity' => 10,
                    'cost' => 50,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);
});
