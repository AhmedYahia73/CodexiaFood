<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Option;
use App\Models\Product;
use App\Models\Tax;
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
