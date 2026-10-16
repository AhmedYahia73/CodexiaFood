<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeManufacturing;
use App\Models\ProductRecipeStock;
use App\Models\Sale;
use App\Models\Saler;
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

    $this->saler = Saler::create([
        'name' => 'تاجر الجملة الشروق',
        'phone' => '01122334455',
        'balance' => 500.00,
    ]);

    $this->category = Category::create([
        'name' => ['ar' => 'وجبات', 'en' => 'Meals'],
        'image' => 'categories/default.png',
    ]);
});

test('unauthenticated request to sale endpoints returns 401', function () {
    $this->getJson('/api/admin/sales')->assertStatus(401);
    $this->getJson('/api/admin/sales/select-options')->assertStatus(401);
    $this->postJson('/api/admin/sales', [])->assertStatus(401);
});

test('admin can fetch select-options for sales returning branches, salers, materials, recipes, and products', function () {
    $material = Material::create(['name' => ['ar' => 'جبنة موزاريلا', 'en' => 'Mozzarella']]);
    $recipe = ProductRecipe::create(['name' => ['ar' => 'عجينة', 'en' => 'Dough']]);
    $product = Product::create([
        'name' => ['ar' => 'بيتزا مارجريتا', 'en' => 'Margherita Pizza'],
        'category_id' => $this->category->id,
        'price' => 120.00,
        'image' => 'products/default.png',
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/sales/select-options?branch_id={$this->branch->id}&lang=ar");

    $res->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches',
                'salers',
                'materials',
                'product_recipes',
                'products',
            ],
        ]);

    expect($res->json('data.materials.0.name'))->toBe('جبنة موزاريلا')
        ->and($res->json('data.product_recipes.0.name'))->toBe('عجينة')
        ->and($res->json('data.products.0.name'))->toBe('بيتزا مارجريتا')
        ->and($res->json('data.salers.0.name'))->toBe('تاجر الجملة الشروق');
});

test('admin can create sale with direct materials and recipes deducting branch stock', function () {
    $material = Material::create(['name' => ['ar' => 'دقيق', 'en' => 'Flour']]);
    $recipe = ProductRecipe::create(['name' => ['ar' => 'صلصة', 'en' => 'Sauce']]);

    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 100,
    ]);

    ProductRecipeStock::create([
        'product_recipe_id' => $recipe->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'paid_amount' => 100.00,
        'due_date' => '2026-12-01',
        'items' => [
            [
                'material_id' => $material->id,
                'quantity' => 20,
                'cost' => 300.00,
            ],
            [
                'product_recipe_id' => $recipe->id,
                'quantity' => 15,
                'cost' => 150.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 450)
        ->assertJsonPath('data.paid_amount', 100)
        ->assertJsonPath('data.remaining_amount', 350)
        ->assertJsonPath('data.dues.0.amount', 350);

    // Stock should be decremented
    expect((float) MaterialStock::where('material_id', $material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(80.0)
        ->and((float) ProductRecipeStock::where('product_recipe_id', $recipe->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(35.0);

    // Saler balance should increase by remaining_amount (500 + 350 = 850)
    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe(850.00);

    $saleId = $response->json('data.id');

    // Deleting the sale should restore stock and reverse saler balance
    $del = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/sales/{$saleId}");

    $del->assertStatus(200);

    expect((float) MaterialStock::where('material_id', $material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(100.0)
        ->and((float) ProductRecipeStock::where('product_recipe_id', $recipe->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(50.0);

    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe(500.00);
});

test('admin selling a product deducts its BOM recipe components', function () {
    $material = Material::create(['name' => ['ar' => 'لحم مفروم', 'en' => 'Minced Meat']]);
    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 20,
    ]);

    $product = Product::create([
        'name' => ['ar' => 'برجر لحم', 'en' => 'Beef Burger'],
        'category_id' => $this->category->id,
        'price' => 80.00,
        'image' => 'products/default.png',
    ]);

    // Create recipe specification: 1 burger requires 2 units of meat
    $spec = ProductManufacturing::create([
        'product_id' => $product->id,
    ]);

    ProductRecipeManufacturing::create([
        'product_manufact_id' => $spec->id,
        'material_id' => $material->id,
        'count' => 2,
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'paid_amount' => 400.00,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5, // Requires 5 * 2 = 10 units of meat
                'cost' => 400.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $response->assertStatus(201);

    // Stock should be 20 - 10 = 10
    expect((float) MaterialStock::where('material_id', $material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(10.0);

    $saleId = $response->json('data.id');

    // Deleting the sale should restore the BOM stock
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/sales/{$saleId}")
        ->assertStatus(200);

    expect((float) MaterialStock::where('material_id', $material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(20.0);
});

test('insufficient recipe component stock returns 422 with can_bypass: true', function () {
    $material = Material::create(['name' => ['ar' => 'شوكولاتة', 'en' => 'Chocolate']]);
    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 3, // only 3 available
    ]);

    $product = Product::create([
        'name' => ['ar' => 'كيك شوكولاتة', 'en' => 'Chocolate Cake'],
        'category_id' => $this->category->id,
        'price' => 100.00,
        'image' => 'products/default.png',
    ]);

    $spec = ProductManufacturing::create([
        'product_id' => $product->id,
    ]);

    ProductRecipeManufacturing::create([
        'product_manufact_id' => $spec->id,
        'material_id' => $material->id,
        'count' => 5, // requires 5 per cake
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2, // Needs 10, but only 3 available!
                'cost' => 200.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $response->assertStatus(422)
        ->assertJsonPath('status', false)
        ->assertJsonPath('insufficient_ingredient.can_bypass', true)
        ->assertJsonPath('insufficient_ingredient.available_stock', 3)
        ->assertJsonPath('insufficient_ingredient.required_quantity', 10);

    // Now send with without_recipe: true -> should succeed and consume available stock without going negative (stock floors at 0)
    $payload['without_recipe'] = true;

    $bypassResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $bypassResponse->assertStatus(201)
        ->assertJsonPath('status', true);

    // Stock should be 0 (consumed 3 available), NOT negative!
    expect((float) MaterialStock::where('material_id', $material->id)->where('branch_id', $this->branch->id)->value('stock'))->toBe(0.0);
});

test('creating sale with multiple installment dues creates multiple sale_dues records', function () {
    $material = Material::create(['name' => ['ar' => 'سكر', 'en' => 'Sugar']]);
    MaterialStock::create([
        'material_id' => $material->id,
        'branch_id' => $this->branch->id,
        'stock' => 50,
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'paid_amount' => 100.00,
        'notes' => 'بيع سكر مع أقساط',
        'dues' => [
            ['amount' => 150.00, 'date' => '2026-11-10', 'notes' => 'دفعة أولى'],
            ['amount' => 150.00, 'date' => '2026-12-10', 'notes' => 'دفعة ثانية'],
        ],
        'items' => [
            [
                'material_id' => $material->id,
                'quantity' => 20,
                'cost' => 400.00,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/sales', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.total_cost', 400)
        ->assertJsonPath('data.paid_amount', 100)
        ->assertJsonPath('data.remaining_amount', 300)
        ->assertJsonCount(2, 'data.dues');

    $saleId = $response->json('data.id');

    // Saler balance incremented by remaining_amount (500 + 300 = 800)
    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe(800.00);

    $this->assertDatabaseCount('sale_dues', 2);
    $this->assertDatabaseHas('sale_dues', [
        'sale_id' => $saleId,
        'amount' => 150.00,
        'notes' => 'دفعة أولى',
    ]);
    $this->assertDatabaseHas('sale_dues', [
        'sale_id' => $saleId,
        'amount' => 150.00,
        'notes' => 'دفعة ثانية',
    ]);

    $sale = Sale::with('dues')->find($saleId);
    expect($sale->dues[0]->date->format('Y-m-d'))->toBe('2026-11-10')
        ->and($sale->dues[1]->date->format('Y-m-d'))->toBe('2026-12-10');
});
