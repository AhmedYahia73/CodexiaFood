<?php

use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeManufacturing;
use App\Models\ProductRecipeStock;
use App\Models\StartShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'status' => true,
    ]);

    $this->otherBranch = Branch::create([
        'name' => ['ar' => 'فرع الزمالك', 'en' => 'Zamalek Branch'],
        'status' => true,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Ahmed Cashier',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);

    $this->shift = StartShift::create([
        'start' => now()->subHour(),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'برجر لحم فاخر', 'en' => 'Gourmet Beef Burger'],
        'price' => 120.00,
        'image' => 'products/burger.jpg',
    ]);

    $this->material = Material::create([
        'name' => ['ar' => 'لحم مفروم', 'en' => 'Minced Beef'],
        'status' => true,
    ]);

    $this->recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
        'status' => true,
    ]);

    // Product manufacturing specification: 1 burger requires 2 units of meat and 1 unit of sauce
    $this->spec = ProductManufacturing::create([
        'product_id' => $this->product->id,
    ]);

    ProductRecipeManufacturing::create([
        'product_manufact_id' => $this->spec->id,
        'material_id' => $this->material->id,
        'count' => 2,
    ]);

    ProductRecipeManufacturing::create([
        'product_manufact_id' => $this->spec->id,
        'product_recipe_id' => $this->recipe->id,
        'count' => 1,
    ]);
});

test('adding to cart fails when branch stock is insufficient for product recipe', function () {
    // Branch has only 1 unit of material in stock, but burger requires 2
    MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 1.0,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $this->recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 5.0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/cashier/cart', [
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => false,
        ])
        ->assertJsonStructure([
            'status',
            'message',
            'insufficient_ingredient' => [
                'type',
                'id',
                'name',
                'available_stock',
                'required_quantity',
                'can_bypass',
            ],
        ]);

    expect($response->json('insufficient_ingredient.type'))->toBe('material')
        ->and((float) $response->json('insufficient_ingredient.available_stock'))->toBe(1.0)
        ->and((float) $response->json('insufficient_ingredient.required_quantity'))->toBe(2.0);
});

test('adding to cart succeeds when without_recipe is true despite insufficient stock', function () {
    // Material stock is 0 in branch
    MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 0.0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/cashier/cart', [
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 1,
        'without_recipe' => true,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
        ]);

    $this->assertDatabaseHas('order_carts', [
        'cashier_id' => $this->cashier->id,
        'product_id' => $this->product->id,
    ]);

    // Ensure stock was NOT deducted during cart store
    expect((float) MaterialStock::where('material_id', $this->material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(0.0);
});

test('adding to cart succeeds when stock is sufficient', function () {
    MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $this->recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 5.0,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/cashier/cart', [
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 2, // Needs 4 material and 2 recipe
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
        ]);
});

test('checkout deducts from branch stock and never goes below zero', function () {
    // Branch has stock: 9 material, 10 recipe
    $matStock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 9.0,
    ]);

    $recStock = ProductRecipeStock::create([
        'product_recipe_id' => $this->recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
    ]);

    // Other branch stock should remain untouched
    $otherMatStock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->otherBranch->id,
        'stock' => 50.0,
    ]);

    // Cart has quantity 5 of product:
    // Requires: 5 * 2 = 10 material
    // Requires: 5 * 1 = 5 recipe
    // Available material is 9. It should deduct 9 and leave stock at 0 (never negative)!
    // Available recipe is 10. It should deduct 5 and leave stock at 5.
    OrderCart::create([
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'branch_id' => $this->branch->id,
        'module' => 'takeaway',
        'product_id' => $this->product->id,
        'quantity' => 5,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->postJson('/api/cashier/orders/checkout', [
        'module' => 'takeaway',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
        ]);

    $matStock->refresh();
    $recStock->refresh();
    $otherMatStock->refresh();

    // Material stock was 9, required was 10 -> deducted 9, now 0 (not negative)!
    expect((float) $matStock->stock)->toBe(0.0)
        // Recipe stock was 10, required was 5 -> deducted 5, now 5!
        ->and((float) $recStock->stock)->toBe(5.0)
        // Other branch stock unchanged
        ->and((float) $otherMatStock->stock)->toBe(50.0);
});
