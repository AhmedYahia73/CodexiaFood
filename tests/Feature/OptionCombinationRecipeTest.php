<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\CaptainOrder;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\FinancialAccount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\StartShift;
use App\Models\Unit;
use App\Services\ProductManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'password' => 'password123',
    ]);
    $this->adminToken = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'status' => true,
    ]);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->table = HallTable::create([
        'name' => 'طاولة 1',
        'code' => 'T101',
        'hall_id' => $this->hall->id,
        'branch_id' => $this->branch->id,
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

    $this->cashierToken = JWTAuth::fromUser($this->cashierMan);

    $this->financialAccount = FinancialAccount::create([
        'name' => ['ar' => 'خزينة رئيسية', 'en' => 'Main Treasury'],
        'status' => true,
        'branch_id' => $this->branch->id,
    ]);

    $this->shift = StartShift::create([
        'start' => now()->subHour(),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'cashier_man_id' => $this->cashierMan->id,
        'financial_account_id' => $this->financialAccount->id,
    ]);

    $this->material = Material::create([
        'name' => ['ar' => 'جبنة موزاريلا', 'en' => 'Mozzarella Cheese'],
        'status' => true,
    ]);

    $this->recipe = ProductRecipe::create([
        'name' => ['ar' => 'عجينة خاصة', 'en' => 'Special Dough'],
        'status' => true,
    ]);
});

test('admin cannot submit partial option recipes: all combinations must be covered if any is provided', function () {
    // 2 variations: Size (Small, Medium) and Bread (Shami, Fino) = 4 combinations
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/products', [
            'name' => ['ar' => 'ساندوتش دجاج', 'en' => 'Chicken Sandwich'],
            'price' => 50,
            'image' => 'products/sandwich.jpg',
            'variations' => [
                [
                    'name' => ['ar' => 'الحجم', 'en' => 'Size'],
                    'options' => [
                        ['name' => ['ar' => 'صغير', 'en' => 'Small'], 'price' => 0],
                        ['name' => ['ar' => 'وسط', 'en' => 'Medium'], 'price' => 10],
                    ],
                ],
                [
                    'name' => ['ar' => 'النوع', 'en' => 'Bread'],
                    'options' => [
                        ['name' => ['ar' => 'شامي', 'en' => 'Shami'], 'price' => 0],
                        ['name' => ['ar' => 'فينو', 'en' => 'Fino'], 'price' => 0],
                    ],
                ],
            ],
            // Only 1 combination provided out of 4:
            'option_recipes' => [
                [
                    'option_ids' => [1, 3], // will be resolved after variation creation
                    'recipes' => [
                        ['material_id' => $this->material->id, 'count' => 1],
                    ],
                ],
            ],
        ]);

    // Should fail with 422 because combinations are incomplete
    $response->assertStatus(422)
        ->assertJsonValidationErrors('option_recipes');
});

test('admin can store a product with variations and recipes for all option combinations', function () {
    // 1. Create a product with 2 variations first
    $product = Product::create([
        'name' => ['ar' => 'ساندوتش كفتة', 'en' => 'Kofta Sandwich'],
        'price' => 60,
        'image' => 'products/kofta.jpg',
    ]);

    $varSize = $product->variations()->create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'status' => true,
    ]);
    $optSmall = $varSize->options()->create([
        'product_id' => $product->id,
        'name' => ['ar' => 'صغير', 'en' => 'Small'],
        'price' => 0,
        'status' => true,
    ]);
    $optMedium = $varSize->options()->create([
        'product_id' => $product->id,
        'name' => ['ar' => 'وسط', 'en' => 'Medium'],
        'price' => 15,
        'status' => true,
    ]);

    $varBread = $product->variations()->create([
        'name' => ['ar' => 'النوع', 'en' => 'Bread'],
        'status' => true,
    ]);
    $optShami = $varBread->options()->create([
        'product_id' => $product->id,
        'name' => ['ar' => 'شامي', 'en' => 'Shami'],
        'price' => 0,
        'status' => true,
    ]);
    $optFino = $varBread->options()->create([
        'product_id' => $product->id,
        'name' => ['ar' => 'فينو', 'en' => 'Fino'],
        'price' => 0,
        'status' => true,
    ]);

    // 4 combinations: (Small+Shami), (Small+Fino), (Medium+Shami), (Medium+Fino)
    $allCombinationsPayload = [
        [
            'option_ids' => [$optSmall->id, $optShami->id],
            'recipes' => [
                ['material_id' => $this->material->id, 'count' => 1],
            ],
        ],
        [
            'option_ids' => [$optSmall->id, $optFino->id],
            'recipes' => [
                ['material_id' => $this->material->id, 'count' => 1],
            ],
        ],
        [
            'option_ids' => [$optMedium->id, $optShami->id],
            'recipes' => [
                ['material_id' => $this->material->id, 'count' => 2],
            ],
        ],
        [
            'option_ids' => [$optMedium->id, $optFino->id],
            'recipes' => [
                ['material_id' => $this->material->id, 'count' => 2],
                ['product_recipe_id' => $this->recipe->id, 'count' => 1],
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/products/{$product->id}", [
            'option_recipes' => $allCombinationsPayload,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    // Verify database has 4 manufacturing specs for this product
    $this->assertDatabaseCount('product_manufacturings', 4);

    $comboKey = ProductManufacturingService::buildCombinationKey([$optMedium->id, $optFino->id]);
    $this->assertDatabaseHas('product_manufacturings', [
        'product_id' => $product->id,
        'option_combination_key' => $comboKey,
    ]);
});

test('cashier cart checks stock for the specific option combination chosen', function () {
    $product = Product::create([
        'name' => ['ar' => 'بيتزا مشكل', 'en' => 'Mixed Pizza'],
        'price' => 100,
        'image' => 'products/pizza.jpg',
    ]);

    $varSize = $product->variations()->create(['name' => ['ar' => 'الحجم', 'en' => 'Size']]);
    $optSmall = $varSize->options()->create(['product_id' => $product->id, 'name' => ['ar' => 'صغير', 'en' => 'Small']]);
    $optLarge = $varSize->options()->create(['product_id' => $product->id, 'name' => ['ar' => 'كبير', 'en' => 'Large']]);

    // Small needs 1 cheese, Large needs 5 cheese
    ProductManufacturingService::syncOptionRecipesForProduct($product, [
        [
            'option_ids' => [$optSmall->id],
            'recipes' => [['material_id' => $this->material->id, 'count' => 1]],
        ],
        [
            'option_ids' => [$optLarge->id],
            'recipes' => [['material_id' => $this->material->id, 'count' => 5]],
        ],
    ]);

    // Branch has only 2 units of cheese
    MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 2.0,
    ]);

    // 1. Adding Small (needs 1) should SUCCEED
    $smallResponse = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $product->id,
            'quantity' => 1,
            'variations' => [
                [
                    'variation_id' => $varSize->id,
                    'option_ids' => [$optSmall->id],
                ],
            ],
        ]);
    $smallResponse->assertStatus(201);

    // 2. Adding Large (needs 5) should FAIL with 422
    $largeResponse = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $product->id,
            'quantity' => 1,
            'variations' => [
                [
                    'variation_id' => $varSize->id,
                    'option_ids' => [$optLarge->id],
                ],
            ],
        ]);

    $largeResponse->assertStatus(422)
        ->assertJsonPath('status', false)
        ->assertJsonPath('insufficient_ingredient.required_quantity', 5);
});

test('cashier cart for dine-in deducts stock on cart store and checkout does not deduct it again', function () {
    $product = Product::create([
        'name' => ['ar' => 'ستيك دجاج', 'en' => 'Chicken Steak'],
        'price' => 150,
        'image' => 'products/steak.jpg',
    ]);

    ProductManufacturingService::syncForProduct($product, [
        ['material_id' => $this->material->id, 'count' => 2],
    ]);

    $stock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
    ]);

    // 1. Adding dine-in item to cashier cart: should deduct 2 * 2 = 4 units immediately
    $response = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/cart', [
            'module' => 'dinein',
            'table_id' => $this->table->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);

    // Stock should now be 10 - 4 = 6
    $stock->refresh();
    expect((float) $stock->stock)->toBe(6.0);

    // 2. Checkout the dine-in cart: should NOT deduct again!
    $checkoutResponse = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'dinein',
            'table_id' => $this->table->id,
            'financial_account_id' => $this->financialAccount->id,
        ]);

    $checkoutResponse->assertStatus(201);

    // Stock must STILL be 6.0, not 2.0!
    $stock->refresh();
    expect((float) $stock->stock)->toBe(6.0);
});

test('takeaway checkout deducts stock at checkout time', function () {
    $product = Product::create([
        'name' => ['ar' => 'شاورما لحم', 'en' => 'Beef Shawarma'],
        'price' => 70,
        'image' => 'products/shawarma.jpg',
    ]);

    ProductManufacturingService::syncForProduct($product, [
        ['material_id' => $this->material->id, 'count' => 3],
    ]);

    $stock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
    ]);

    // 1. Adding takeaway item to cart: should NOT deduct stock yet
    $response = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/cart', [
            'module' => 'takeaway',
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertStatus(201);

    $stock->refresh();
    expect((float) $stock->stock)->toBe(10.0);

    // 2. Checkout takeaway order: should deduct 2 * 3 = 6 units
    $checkoutResponse = $this->withHeader('Authorization', 'Bearer '.$this->cashierToken)
        ->postJson('/api/cashier/orders/checkout', [
            'module' => 'takeaway',
            'financial_account_id' => $this->financialAccount->id,
        ]);

    $checkoutResponse->assertStatus(201);

    $stock->refresh();
    expect((float) $stock->stock)->toBe(4.0);
});

test('captain cart adds dine-in item and stock is deducted immediately upon dispatch', function () {
    $captain = CaptainOrder::create([
        'name' => 'Captain Samy',
        'user_name' => 'samy_captain',
        'password' => 'secret123',
        'status' => true,
    ]);
    $captainToken = JWTAuth::fromUser($captain);

    $product = Product::create([
        'name' => ['ar' => 'سلطة سيزر', 'en' => 'Caesar Salad'],
        'price' => 45,
        'image' => 'products/salad.jpg',
    ]);

    ProductManufacturingService::syncForProduct($product, [
        ['material_id' => $this->material->id, 'count' => 1],
    ]);

    $stock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $this->branch->id,
        'stock' => 10.0,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$captainToken)
        ->postJson('/api/captain/cart', [
            'table_id' => $this->table->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

    $response->assertStatus(201);

    // Stock should be deducted immediately: 10 - 3 = 7
    $stock->refresh();
    expect((float) $stock->stock)->toBe(7.0);
});

test('table order cart adds dine-in item and stock is deducted immediately upon dispatch', function () {
    $geoBranch = Branch::create([
        'name' => ['ar' => 'فرع جغرافي', 'en' => 'Geo Branch'],
        'status' => true,
        'location' => [
            ['lat' => 30.0, 'lng' => 31.0],
            ['lat' => 30.0, 'lng' => 32.0],
            ['lat' => 31.0, 'lng' => 32.0],
            ['lat' => 31.0, 'lng' => 31.0],
        ],
    ]);

    $geoTable = HallTable::create([
        'name' => 'طاولة جغرافية',
        'code' => 'TG101',
        'branch_id' => $geoBranch->id,
        'status' => true,
    ]);

    $product = Product::create([
        'name' => ['ar' => 'مشروب غازي', 'en' => 'Soft Drink'],
        'price' => 20,
        'image' => 'products/drink.jpg',
    ]);

    ProductManufacturingService::syncForProduct($product, [
        ['material_id' => $this->material->id, 'count' => 1],
    ]);

    $stock = MaterialStock::create([
        'material_id' => $this->material->id,
        'branch_id' => $geoBranch->id,
        'stock' => 5.0,
    ]);

    $response = $this->postJson('/api/table-order/cart', [
        'table_id' => $geoTable->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'lat' => 30.5,
        'lng' => 31.5,
    ]);

    $response->assertStatus(201);

    // Stock should be deducted immediately: 5 - 2 = 3
    $stock->refresh();
    expect((float) $stock->stock)->toBe(3.0);
});

test('creating product with variations but without recipes is allowed (recipes are optional)', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/products', [
            'name' => ['ar' => 'عصير برتقال', 'en' => 'Orange Juice'],
            'price' => 30,
            'image' => 'products/juice.jpg',
            'variations' => [
                [
                    'name' => ['ar' => 'الحجم', 'en' => 'Size'],
                    'options' => [
                        ['name' => ['ar' => 'صغير', 'en' => 'Small'], 'price' => 0],
                        ['name' => ['ar' => 'كبير', 'en' => 'Large'], 'price' => 10],
                    ],
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    $this->assertDatabaseHas('products', [
        'name->en' => 'Orange Juice',
    ]);
});

test('creating product without variations can still use simple recipes array (backward compatibility)', function () {
    $unit = Unit::create([
        'name' => ['ar' => 'جرام', 'en' => 'Gram'],
        'value' => 1,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/products', [
            'name' => ['ar' => 'بطاطس مقلية', 'en' => 'French Fries'],
            'price' => 25,
            'image' => 'products/fries.jpg',
            'recipes' => [
                [
                    'material_id' => $this->material->id,
                    'count' => 1,
                    'unit_id' => $unit->id,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true);

    $productId = $response->json('data.id');

    $this->assertDatabaseHas('product_manufacturings', [
        'product_id' => $productId,
        'option_combination_key' => null,
    ]);
});
