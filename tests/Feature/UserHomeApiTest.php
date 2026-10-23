<?php

use App\Models\Addon;
use App\Models\BusinessSetup;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Option;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->parentCat = Category::create([
        'name' => ['ar' => 'وجبات رئيسية', 'en' => 'Main Dishes'],
        'description' => ['ar' => 'وصف القسم', 'en' => 'Category Desc'],
        'image' => 'categories/main.jpg',
        'status' => true,
        'type' => 'product',
    ]);

    $this->subCat = Category::create([
        'category_id' => $this->parentCat->id,
        'name' => ['ar' => 'برجر', 'en' => 'Burgers'],
        'description' => ['ar' => 'وصف فرعي', 'en' => 'Sub Desc'],
        'image' => 'categories/burgers.jpg',
        'status' => true,
        'type' => 'product',
    ]);

    $this->discount = Discount::create([
        'name' => ['ar' => 'خصم 10%', 'en' => '10% Discount'],
        'type' => 'percentage',
        'amount' => 10,
        'status' => true,
    ]);

    $this->tax = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => '14% VAT'],
        'type' => 'percentage',
        'amount' => 14,
        'status' => true,
    ]);

    $this->product = Product::create([
        'name' => ['ar' => 'برجر لحم', 'en' => 'Beef Burger'],
        'description' => ['ar' => 'وصف البرجر', 'en' => 'Burger Description'],
        'image' => 'products/burger.jpg',
        'category_id' => $this->parentCat->id,
        'sub_category_id' => $this->subCat->id,
        'price' => 100.00,
        'discount_id' => $this->discount->id,
        'tax_id' => $this->tax->id,
        'stock' => 50,
    ]);

    $this->variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $this->product->id,
        'status' => true,
        'required' => true,
    ]);

    $this->option = Option::create([
        'name' => ['ar' => 'دابل', 'en' => 'Double'],
        'product_id' => $this->product->id,
        'variation_id' => $this->variation->id,
        'price' => 30.00,
        'status' => true,
    ]);

    $this->addon = Addon::create([
        'name' => ['ar' => 'بطاطس', 'en' => 'Fries'],
        'image' => 'addons/fries.jpg',
        'price' => 20.00,
        'discount_id' => $this->discount->id,
        'tax_id' => $this->tax->id,
    ]);

    $this->businessSetup = BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business/logo.png',
        'description' => 'أفضل تجربة طعام لجميع العائلة',
    ]);
});

test('public user can fetch parent categories without authentication', function () {
    $response = $this->getJson('/api/user/categories/parents?lang=ar')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'description', 'image', 'status', 'type'],
            ],
        ]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($this->parentCat->id);
    expect($ids)->not->toContain($this->subCat->id);
});

test('public user can fetch sub categories without authentication and filter by category_id', function () {
    $response = $this->getJson('/api/user/categories/sub?category_id='.$this->parentCat->id.'&lang=ar')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'category_id', 'name', 'description', 'image', 'status', 'type'],
            ],
        ]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($this->subCat->id);
});

test('public user can fetch products and filter by category without authentication', function () {
    $response = $this->getJson('/api/user/products?category_id='.$this->parentCat->id.'&lang=ar')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => [
                    'id', 'name', 'description', 'image', 'category_id', 'sub_category_id',
                    'price', 'discount_val', 'tax_val', 'final_price', 'discount', 'tax',
                ],
            ],
        ]);

    $products = collect($response->json('data'));
    expect($products->pluck('id'))->toContain($this->product->id);
});

test('public user can fetch product details without authentication', function () {
    $response = $this->getJson('/api/user/products/'.$this->product->id.'?lang=ar')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $this->product->id,
                'name' => 'برجر لحم',
                'category_id' => $this->parentCat->id,
                'sub_category_id' => $this->subCat->id,
            ],
        ])
        ->assertJsonStructure([
            'status',
            'data' => [
                'id', 'name', 'description', 'image', 'price', 'discount_val', 'tax_val', 'final_price',
                'variations' => [
                    '*' => [
                        'id', 'name', 'status', 'required',
                        'options' => [
                            '*' => ['id', 'name', 'price', 'discount_val', 'tax_val', 'final_price', 'status'],
                        ],
                    ],
                ],
            ],
        ]);
});

test('public user can fetch addons without authentication', function () {
    $response = $this->getJson('/api/user/addons?lang=ar')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'image', 'price', 'discount_val', 'tax_val', 'final_price'],
            ],
        ]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($this->addon->id);
});

test('public user can fetch business setup without authentication', function () {
    $this->businessSetup->update([
        'menu' => ['business_setup/menu1.png', 'https://example.com/menu2.png'],
    ]);

    $response = $this->getJson('/api/user/business-setup')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'menu',
                'raw_menu',
            ],
        ]);

    expect($response->json('status'))->toBeTrue()
        ->and($response->json('data.menu'))->toHaveCount(2)
        ->and($response->json('data.menu.0'))->toBe(url('storage/business_setup/menu1.png'))
        ->and($response->json('data.menu.1'))->toBe('https://example.com/menu2.png');
});

test('public user can fetch menu without authentication token', function () {
    $this->businessSetup->update([
        'menu' => ['business_setup/menu1.png', 'business_setup/menu2.png'],
    ]);

    $response = $this->getJson('/api/user/menu')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                url('storage/business_setup/menu1.png'),
                url('storage/business_setup/menu2.png'),
            ],
        ]);

    expect($response->json('data'))->toHaveCount(2);
});

test('public user receives empty array when no menu images exist', function () {
    $this->businessSetup->update([
        'menu' => null,
    ]);

    $response = $this->getJson('/api/user/menu')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [],
        ]);
});

test('public user can search parent categories by search key', function () {
    Category::create([
        'name' => ['ar' => 'مشروبات ساخنة', 'en' => 'Hot Drinks'],
        'description' => ['ar' => 'شاي وقهوة', 'en' => 'Tea and coffee'],
        'category_id' => null,
        'image' => 'categories/hot_drinks.jpg',
        'status' => true,
    ]);

    $response = $this->getJson('/api/user/categories/parents?search=مشروبات&lang=ar')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('مشروبات ساخنة');

    $enResponse = $this->getJson('/api/user/categories/parents?search=Drinks&lang=en')
        ->assertStatus(200);

    expect($enResponse->json('data'))->toHaveCount(1)
        ->and($enResponse->json('data.0.name'))->toBe('Hot Drinks');

    $emptyResponse = $this->getJson('/api/user/categories/parents?search=NonExistentKey')
        ->assertStatus(200);

    expect($emptyResponse->json('data'))->toHaveCount(0);
});

test('public user can search sub categories by search key', function () {
    Category::create([
        'category_id' => $this->parentCat->id,
        'name' => ['ar' => 'بيتزا', 'en' => 'Pizza'],
        'description' => ['ar' => 'بيتزا إيطالية', 'en' => 'Italian Pizza'],
        'image' => 'categories/pizza.jpg',
        'status' => true,
    ]);

    $response = $this->getJson('/api/user/categories/sub?search=بيتزا&lang=ar')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('بيتزا');

    $enResponse = $this->getJson('/api/user/categories/sub?search=Pizza&lang=en')
        ->assertStatus(200);

    expect($enResponse->json('data'))->toHaveCount(1)
        ->and($enResponse->json('data.0.name'))->toBe('Pizza');
});

test('public user can search products by search key', function () {
    Product::create([
        'name' => ['ar' => 'بيتزا خضار', 'en' => 'Veggie Pizza'],
        'description' => ['ar' => 'بيتزا طازجة', 'en' => 'Fresh pizza'],
        'category_id' => $this->parentCat->id,
        'sub_category_id' => $this->subCat->id,
        'image' => 'products/veggie_pizza.jpg',
        'price' => 80.00,
        'stock' => 20,
    ]);

    $response = $this->getJson('/api/user/products?search=خضار&lang=ar')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('بيتزا خضار');

    $enResponse = $this->getJson('/api/user/products?search=Veggie&lang=en')
        ->assertStatus(200);

    expect($enResponse->json('data'))->toHaveCount(1)
        ->and($enResponse->json('data.0.name'))->toBe('Veggie Pizza');

    $noMatchResponse = $this->getJson('/api/user/products?search=RandomKeyword123')
        ->assertStatus(200);

    expect($noMatchResponse->json('data'))->toHaveCount(0);
});

test('public user can search addons by search key', function () {
    Addon::create([
        'name' => ['ar' => 'صوص جبنة', 'en' => 'Cheese Sauce'],
        'image' => 'addons/cheese.jpg',
        'price' => 15.00,
    ]);

    $response = $this->getJson('/api/user/addons?search=جبنة&lang=ar')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('صوص جبنة');

    $enResponse = $this->getJson('/api/user/addons?search=Cheese&lang=en')
        ->assertStatus(200);

    expect($enResponse->json('data'))->toHaveCount(1)
        ->and($enResponse->json('data.0.name'))->toBe('Cheese Sauce');

    $noMatchResponse = $this->getJson('/api/user/addons?search=NonExistentAddon')
        ->assertStatus(200);

    expect($noMatchResponse->json('data'))->toHaveCount(0);
});
