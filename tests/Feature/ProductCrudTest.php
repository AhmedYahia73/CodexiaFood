<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipe;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Variation;
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

test('unauthenticated request to product endpoints returns 401', function () {
    $this->getJson('/api/admin/products')->assertStatus(401);
    $this->getJson('/api/admin/products/select-options')->assertStatus(401);
    $this->postJson('/api/admin/products', [])->assertStatus(401);
});

test('admin can fetch dedicated select-options containing tax, discount, parent_categories, and sub_categories', function () {
    $tax = Tax::create([
        'name' => ['ar' => 'ضريبة القيمة المضافة', 'en' => 'VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $discount = Discount::create([
        'name' => ['ar' => 'خصم الافتتاح', 'en' => 'Opening Discount'],
        'type' => 'percentage',
        'amount' => 10.00,
        'status' => true,
    ]);

    $parentCategory = Category::create([
        'name' => ['ar' => 'المأكولات الرئيسية', 'en' => 'Main Dishes'],
        'image' => 'categories/main.jpg',
        'category_id' => null,
        'type' => 'product',
        'status' => true,
    ]);

    $subCategory = Category::create([
        'name' => ['ar' => 'برجر', 'en' => 'Burgers'],
        'image' => 'categories/burger.jpg',
        'category_id' => $parentCategory->id,
        'type' => 'product',
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/products/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'tax' => [
                    '*' => ['id', 'name', 'type', 'amount'],
                ],
                'discount' => [
                    '*' => ['id', 'name', 'type', 'amount'],
                ],
                'parent_categories' => [
                    '*' => ['id', 'name', 'type'],
                ],
                'sub_categories' => [
                    '*' => ['id', 'name', 'category_id', 'type'],
                ],
            ],
        ]);

    expect($response->json('data.tax'))->toHaveCount(1)
        ->and($response->json('data.discount'))->toHaveCount(1)
        ->and($response->json('data.parent_categories'))->toHaveCount(1)
        ->and($response->json('data.sub_categories'))->toHaveCount(1)
        ->and($response->json('data.parent_categories.0.id'))->toBe($parentCategory->id)
        ->and($response->json('data.sub_categories.0.id'))->toBe($subCategory->id)
        ->and($response->json('data.sub_categories.0.category_id'))->toBe($parentCategory->id);
});

test('admin can perform full CRUD on Product with nested variations and options', function () {
    Storage::fake('public');

    $tax = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => 'Tax 14%'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $discount = Discount::create([
        'name' => ['ar' => 'خصم 5%', 'en' => 'Discount 5%'],
        'type' => 'value',
        'amount' => 5.00,
        'status' => true,
    ]);

    $parentCategory = Category::create([
        'name' => ['ar' => 'برجر', 'en' => 'Burgers'],
        'image' => 'categories/parent.jpg',
        'category_id' => null,
    ]);

    $subCategory = Category::create([
        'name' => ['ar' => 'برجر لحم', 'en' => 'Beef Burgers'],
        'image' => 'categories/sub.jpg',
        'category_id' => $parentCategory->id,
    ]);

    $imageFile = UploadedFile::fake()->image('burger.jpg');

    // 1. Create (Store)
    $payload = [
        'name' => [
            'ar' => 'برجر دبل تشيز',
            'en' => 'Double Cheese Burger',
        ],
        'description' => [
            'ar' => 'برجر لحم فاخر مع جبنة شيدر مضاعفة',
            'en' => 'Premium beef burger with double cheddar cheese',
        ],
        'price' => 150.00,
        'tax_id' => $tax->id,
        'discount_id' => $discount->id,
        'category_id' => $parentCategory->id,
        'sub_category_id' => $subCategory->id,
        'image' => $imageFile,
        'variations' => [
            [
                'name' => ['ar' => 'الحجم', 'en' => 'Size'],
                'status' => true,
                'required' => true,
                'options' => [
                    [
                        'name' => ['ar' => 'وسط', 'en' => 'Medium'],
                        'price' => 0,
                        'status' => true,
                    ],
                    [
                        'name' => ['ar' => 'كبير', 'en' => 'Large'],
                        'price' => 30,
                        'status' => true,
                    ],
                ],
            ],
            [
                'name' => ['ar' => 'إضافات', 'en' => 'Addons'],
                'status' => true,
                'required' => false,
                'options' => [
                    [
                        'name' => ['ar' => 'جبنة إضافية', 'en' => 'Extra Cheese'],
                        'price' => 15,
                        'status' => true,
                    ],
                ],
            ],
        ],
    ];

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/products', $payload);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'برجر دبل تشيز')
        ->assertJsonPath('data.name.en', 'Double Cheese Burger')
        ->assertJsonPath('data.price', 150)
        ->assertJsonPath('data.tax_id', $tax->id)
        ->assertJsonPath('data.discount_id', $discount->id)
        ->assertJsonPath('data.category_id', $parentCategory->id)
        ->assertJsonPath('data.sub_category_id', $subCategory->id)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'name',
                'description',
                'image',
                'price',
                'tax_id',
                'tax',
                'discount_id',
                'discount',
                'category_id',
                'category',
                'sub_category_id',
                'sub_category',
                'variations' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'required',
                        'options' => [
                            '*' => ['id', 'name', 'price', 'status'],
                        ],
                    ],
                ],
            ],
            'select_options' => [
                'tax',
                'discount',
                'parent_categories',
                'sub_categories',
            ],
        ]);

    $productId = $storeResponse->json('data.id');
    expect($productId)->not->toBeNull();

    $this->assertDatabaseHas('products', [
        'id' => $productId,
        'sub_category_id' => $subCategory->id,
    ]);

    expect(Variation::where('product_id', $productId)->count())->toBe(2);
    expect(Option::where('product_id', $productId)->count())->toBe(3);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/products/{$productId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $productId)
        ->assertJsonPath('data.sub_category_id', $subCategory->id)
        ->assertJsonPath('data.sub_category.id', $subCategory->id)
        ->assertJsonPath('data.category.id', $parentCategory->id)
        ->assertJsonPath('data.tax.id', $tax->id)
        ->assertJsonPath('data.discount.id', $discount->id)
        ->assertJsonStructure(['select_options' => ['tax', 'discount', 'parent_categories', 'sub_categories']]);

    expect($showResponse->json('data.variations'))->toHaveCount(2);

    // 3. Update (sync variations & options, update sub_category_id and price)
    $newSubCategory = Category::create([
        'name' => ['ar' => 'سندوتشات مميزة', 'en' => 'Special Sandwiches'],
        'image' => 'categories/special.jpg',
        'category_id' => $parentCategory->id,
    ]);

    $newImage = UploadedFile::fake()->image('updated_burger.jpg');

    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/products/{$productId}", [
            'name' => [
                'ar' => 'برجر تربل تشيز معدل',
                'en' => 'Triple Cheese Burger Updated',
            ],
            'price' => 199.99,
            'sub_category_id' => $newSubCategory->id,
            'image' => $newImage,
            'variations' => [
                [
                    'name' => ['ar' => 'الحجم الجديد', 'en' => 'New Size'],
                    'status' => true,
                    'required' => true,
                    'options' => [
                        [
                            'name' => ['ar' => 'جامبو', 'en' => 'Jumbo'],
                            'price' => 45.00,
                            'status' => true,
                        ],
                    ],
                ],
            ],
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'برجر تربل تشيز معدل')
        ->assertJsonPath('data.name.en', 'Triple Cheese Burger Updated')
        ->assertJsonPath('data.price', 199.99)
        ->assertJsonPath('data.sub_category_id', $newSubCategory->id)
        ->assertJsonPath('data.sub_category.id', $newSubCategory->id);

    // Verify database was updated and variations/options synchronized
    $this->assertDatabaseHas('products', [
        'id' => $productId,
        'sub_category_id' => $newSubCategory->id,
    ]);

    expect(Variation::where('product_id', $productId)->count())->toBe(1);
    expect(Option::where('product_id', $productId)->count())->toBe(1);
    expect(Option::where('product_id', $productId)->first()->name['en'])->toBe('Jumbo');

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/products/{$productId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('products', ['id' => $productId]);
    $this->assertDatabaseMissing('variations', ['product_id' => $productId]);
    $this->assertDatabaseMissing('options', ['product_id' => $productId]);
});

test('admin can create product with string name and string variations/options', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/products', [
            'name' => 'Chicken Shawarma',
            'description' => 'Delicious shawarma plate',
            'price' => 85.50,
            'image' => 'uploads/products/shawarma.jpg',
            'variations' => [
                [
                    'name' => 'Spiciness',
                    'options' => [
                        [
                            'name' => 'Mild',
                            'price' => 0,
                        ],
                        [
                            'name' => 'Spicy',
                            'price' => 5,
                        ],
                    ],
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.en', 'Chicken Shawarma')
        ->assertJsonPath('data.name.ar', 'Chicken Shawarma')
        ->assertJsonPath('data.variations.0.name.en', 'Spiciness')
        ->assertJsonPath('data.variations.0.options.0.name.en', 'Mild')
        ->assertJsonPath('data.variations.0.options.1.price', 5);
});

test('admin can list products with pagination and select_options', function () {
    Product::create([
        'name' => ['ar' => 'منتج تجريبي', 'en' => 'Test Product'],
        'price' => 50,
        'image' => 'uploads/products/test.jpg',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/products');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'price',
                    'image',
                    'category',
                    'sub_category',
                    'variations',
                ],
            ],
            'links',
            'meta',
            'select_options' => [
                'tax',
                'discount',
                'parent_categories',
                'sub_categories',
            ],
        ]);
});

test('admin can fetch dedicated select-options containing materials, product_recipes, and units', function () {
    $material = Material::create([
        'name' => ['ar' => 'طماطم', 'en' => 'Tomato'],
    ]);

    $recipe = ProductRecipe::create([
        'name' => ['ar' => 'صلصة خاصة', 'en' => 'Special Sauce'],
    ]);

    $unit = Unit::create([
        'name' => ['ar' => 'جرام', 'en' => 'Gram'],
        'value' => 1,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/products/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'tax',
                'discount',
                'parent_categories',
                'sub_categories',
                'materials' => [
                    '*' => ['id', 'name'],
                ],
                'product_recipes' => [
                    '*' => ['id', 'name'],
                ],
                'units' => [
                    '*' => ['id', 'name', 'value'],
                ],
            ],
        ]);

    expect($response->json('data.materials'))->not->toBeEmpty()
        ->and($response->json('data.product_recipes'))->not->toBeEmpty()
        ->and($response->json('data.units'))->not->toBeEmpty();
});

test('admin can create and update product with ingredients/recipes automatically and view them by id', function () {
    $material = Material::create([
        'name' => ['ar' => 'لحم مفروم', 'en' => 'Minced Meat'],
    ]);

    $productRecipe = ProductRecipe::create([
        'name' => ['ar' => 'خلطة توابل', 'en' => 'Spice Mix'],
    ]);

    $unit = Unit::create([
        'name' => ['ar' => 'قطعة', 'en' => 'Piece'],
        'value' => 1,
    ]);

    // 1. Store Product with components
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/products', [
            'name' => ['en' => 'Burger Combo', 'ar' => 'وجبة برجر'],
            'price' => 120.00,
            'image' => 'products/burger-combo.jpg',
            'recipes' => [
                [
                    'material_id' => $material->id,
                    'count' => 2,
                    'unit_id' => $unit->id,
                ],
                [
                    'product_recipe_id' => $productRecipe->id,
                    'count' => 1,
                    'unit_id' => $unit->id,
                ],
            ],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.en', 'Burger Combo')
        ->assertJsonCount(2, 'data.recipes')
        ->assertJsonPath('data.recipes.0.material_id', $material->id)
        ->assertJsonPath('data.recipes.0.count', 2)
        ->assertJsonPath('data.recipes.1.product_recipe_id', $productRecipe->id)
        ->assertJsonPath('data.product_manufacturing.product_id', $storeResponse->json('data.id'));

    $productId = $storeResponse->json('data.id');
    expect($productId)->not->toBeNull();

    // Verify DB
    $spec = ProductManufacturing::where('product_id', $productId)->first();
    expect($spec)->not->toBeNull()
        ->and($spec->productRecipeManufacturings()->count())->toBe(2);

    // 2. Fetch single product by Show ID
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/products/{$productId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $productId)
        ->assertJsonCount(2, 'data.recipes')
        ->assertJsonPath('data.product_manufacturing.id', $spec->id);

    // 3. Fetch all products index without ID (compact response without recipes)
    $indexAllResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/products');

    $indexAllResponse->assertStatus(200);
    $firstProduct = $indexAllResponse->json('data.0');
    expect(array_key_exists('recipes', $firstProduct))->toBeFalse()
        ->and(array_key_exists('product_manufacturing', $firstProduct))->toBeFalse();

    // 4. Fetch products index filtered by ID (includes recipes)
    $indexFilteredResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/products?id={$productId}");

    $indexFilteredResponse->assertStatus(200)
        ->assertJsonCount(1, 'data');
    $filteredProduct = $indexFilteredResponse->json('data.0');
    expect(array_key_exists('recipes', $filteredProduct))->toBeTrue()
        ->and($filteredProduct['recipes'])->toHaveCount(2)
        ->and(array_key_exists('product_manufacturing', $filteredProduct))->toBeTrue();

    // 5. Update Product recipes
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/products/{$productId}", [
            'price' => 135.00,
            'recipes' => [
                [
                    'material_id' => $material->id,
                    'count' => 3,
                    'unit_id' => $unit->id,
                ],
            ],
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.price', 135)
        ->assertJsonCount(1, 'data.recipes')
        ->assertJsonPath('data.recipes.0.count', 3);

    expect($spec->fresh()->productRecipeManufacturings()->count())->toBe(1);

    // 6. Delete Product and verify cascade cleanup
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/products/{$productId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('products', ['id' => $productId]);
    $this->assertDatabaseMissing('product_manufacturings', ['id' => $spec->id]);
});
