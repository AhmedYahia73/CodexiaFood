<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\CashierMan;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع الرئيسي', 'en' => 'Main Branch'],
        'status' => true,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Cashier Operator',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);
});

test('unauthenticated request to cashier endpoints returns 401', function () {
    $this->getJson('/api/cashier/categories/parents')->assertStatus(401);
    $this->getJson('/api/cashier/categories/sub')->assertStatus(401);
    $this->getJson('/api/cashier/products')->assertStatus(401);
    $this->getJson('/api/cashier/addons')->assertStatus(401);
    $this->getJson('/api/cashier/halls')->assertStatus(401);
    $this->getJson('/api/cashier/hall-tables')->assertStatus(401);
});

test('cashier can fetch parent categories where category_id is null', function () {
    $parentCategory = Category::create([
        'name' => ['ar' => 'الوجبات الرئيسية', 'en' => 'Main Meals'],
        'description' => ['ar' => 'وصف القسم الرئيسي', 'en' => 'Main description'],
        'category_id' => null,
        'image' => 'categories/main.jpg',
        'status' => true,
    ]);

    $subCategory = Category::create([
        'name' => ['ar' => 'ساندوتشات', 'en' => 'Sandwiches'],
        'description' => ['ar' => 'قسم فرعي', 'en' => 'Sub description'],
        'category_id' => $parentCategory->id,
        'image' => 'categories/sub.jpg',
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/categories/parents?lang=ar')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($parentCategory->id);
    expect($ids)->not->toContain($subCategory->id);

    $categoryData = collect($response->json('data'))->firstWhere('id', $parentCategory->id);
    expect($categoryData['name'])->toBe('الوجبات الرئيسية');
    expect($categoryData['description'])->toBe('وصف القسم الرئيسي');
});

test('cashier can fetch sub categories and filter by parent category_id', function () {
    $parent1 = Category::create([
        'name' => ['ar' => 'أطباق لحوم', 'en' => 'Meat Dishes'],
        'category_id' => null,
        'image' => 'categories/meat.jpg',
        'status' => true,
    ]);

    $parent2 = Category::create([
        'name' => ['ar' => 'مشروبات', 'en' => 'Beverages'],
        'category_id' => null,
        'image' => 'categories/drinks.jpg',
        'status' => true,
    ]);

    $sub1 = Category::create([
        'name' => ['ar' => 'ستيك', 'en' => 'Steak'],
        'category_id' => $parent1->id,
        'image' => 'categories/steak.jpg',
        'status' => true,
    ]);

    $sub2 = Category::create([
        'name' => ['ar' => 'عصائر طازجة', 'en' => 'Fresh Juices'],
        'category_id' => $parent2->id,
        'image' => 'categories/juice.jpg',
        'status' => true,
    ]);

    // Test filter by parent1
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/categories/sub?category_id='.$parent1->id.'&lang=en')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($sub1->id);
    expect($ids)->not->toContain($sub2->id);

    $subData = collect($response->json('data'))->firstWhere('id', $sub1->id);
    expect($subData['name'])->toBe('Steak');
});

test('cashier can fetch products with percentage discount and tax calculated after discount', function () {
    // 20% discount
    $discount = Discount::create([
        'name' => ['ar' => 'خصم 20%', 'en' => '20% Discount'],
        'type' => 'percentage',
        'amount' => 20.00,
        'status' => true,
    ]);

    // 14% tax on price after discount
    $tax = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => '14% VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    // Product price = 100
    // discount_val = 100 * 20% = 20
    // price after discount = 100 - 20 = 80
    // tax_val = 80 * 14% = 11.20
    // final_price = 100 - 20 + 11.20 = 91.20
    $product = Product::create([
        'name' => ['ar' => 'برجر لحم فاخر', 'en' => 'Gourmet Beef Burger'],
        'description' => ['ar' => 'وصف المنتج', 'en' => 'Product description'],
        'price' => 100.00,
        'image' => 'products/beef.jpg',
        'discount_id' => $discount->id,
        'tax_id' => $tax->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/products?lang=ar')
        ->assertStatus(200);

    $item = collect($response->json('data'))->firstWhere('id', $product->id);
    expect($item['name'])->toBe('برجر لحم فاخر');
    expect((float) $item['price'])->toBe(100.0);
    expect((float) $item['discount_val'])->toBe(20.0);
    expect((float) $item['tax_val'])->toBe(11.2);
    expect((float) $item['final_price'])->toBe(91.2);
});

test('cashier can fetch products with fixed value discount and fixed tax', function () {
    // 15 fixed discount
    $discount = Discount::create([
        'name' => ['ar' => 'خصم 15 جنيه', 'en' => '15 EGP Off'],
        'type' => 'value',
        'amount' => 15.00,
        'status' => true,
    ]);

    // 5 fixed tax
    $tax = Tax::create([
        'name' => ['ar' => 'ضريبة 5 جنيه', 'en' => '5 EGP Tax'],
        'type' => 'value',
        'amount' => 5.00,
        'status' => true,
    ]);

    // Product price = 200
    // discount_val = 15
    // price after discount = 185
    // tax_val = 5
    // final_price = 200 - 15 + 5 = 190
    $product = Product::create([
        'name' => ['ar' => 'بيتزا مشكل جبن', 'en' => 'Mix Cheese Pizza'],
        'price' => 200.00,
        'image' => 'products/pizza.jpg',
        'discount_id' => $discount->id,
        'tax_id' => $tax->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/products?lang=en')
        ->assertStatus(200);

    $item = collect($response->json('data'))->firstWhere('id', $product->id);
    expect($item['name'])->toBe('Mix Cheese Pizza');
    expect((float) $item['price'])->toBe(200.0);
    expect((float) $item['discount_val'])->toBe(15.0);
    expect((float) $item['tax_val'])->toBe(5.0);
    expect((float) $item['final_price'])->toBe(190.0);
});

test('cashier can fetch product details with variations and options where options inherit percentage discount', function () {
    // 10% discount on product
    $discount = Discount::create([
        'name' => ['ar' => 'خصم 10%', 'en' => '10% Off'],
        'type' => 'percentage',
        'amount' => 10.00,
        'status' => true,
    ]);

    // 14% tax on product
    $tax = Tax::create([
        'name' => ['ar' => 'ضريبة 14%', 'en' => '14% VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $product = Product::create([
        'name' => ['ar' => 'قهوة اسبريسو', 'en' => 'Espresso Coffee'],
        'price' => 50.00,
        'image' => 'products/coffee.jpg',
        'discount_id' => $discount->id,
        'tax_id' => $tax->id,
    ]);

    $variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $product->id,
        'status' => true,
        'required' => true,
    ]);

    // Option price = 30
    // Inherited 10% discount: 30 * 10% = 3.00
    // Price after discount = 27
    // 14% tax: 27 * 14% = 3.78
    // Final price = 30 - 3 + 3.78 = 30.78
    $option = Option::create([
        'name' => ['ar' => 'دبل', 'en' => 'Double'],
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'price' => 30.00,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/products/'.$product->id.'?lang=ar')
        ->assertStatus(200);

    $productData = $response->json('data');
    expect($productData['name'])->toBe('قهوة اسبريسو');
    expect((float) $productData['price'])->toBe(50.0);
    expect((float) $productData['discount_val'])->toBe(5.0);
    expect((float) $productData['tax_val'])->toBe(6.3); // 45 * 14% = 6.30
    expect((float) $productData['final_price'])->toBe(51.3);

    $optionData = $productData['variations'][0]['options'][0];
    expect($optionData['name'])->toBe('دبل');
    expect((float) $optionData['price'])->toBe(30.0);
    expect((float) $optionData['discount_val'])->toBe(3.0);
    expect((float) $optionData['tax_val'])->toBe(3.78);
    expect((float) $optionData['final_price'])->toBe(30.78);
});

test('cashier can fetch addons with discount_val, tax_val, and final_price', function () {
    $discount = Discount::create([
        'name' => ['ar' => 'خصم 50%', 'en' => '50% Off'],
        'type' => 'percentage',
        'amount' => 50.00,
        'status' => true,
    ]);

    // Addon price = 20, discount 50% = 10, final = 10
    $addon = Addon::create([
        'name' => ['ar' => 'صلصة ثومية', 'en' => 'Garlic Sauce'],
        'price' => 20.00,
        'image' => 'addons/sauce.jpg',
        'discount_id' => $discount->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/addons?lang=en')
        ->assertStatus(200);

    $item = collect($response->json('data'))->firstWhere('id', $addon->id);
    expect($item['name'])->toBe('Garlic Sauce');
    expect((float) $item['price'])->toBe(20.0);
    expect((float) $item['discount_val'])->toBe(10.0);
    expect((float) $item['final_price'])->toBe(10.0);
});

test('cashier can fetch halls and hall-tables with hall_id filter', function () {
    $hall1 = Hall::create([
        'name' => ['ar' => 'صالة العائلات', 'en' => 'Family Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $hall2 = Hall::create([
        'name' => ['ar' => 'صالة الأفراد', 'en' => 'Singles Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $t1 = HallTable::create([
        'name' => 'F1',
        'hall_id' => $hall1->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $t2 = HallTable::create([
        'name' => 'S1',
        'hall_id' => $hall2->id,
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    // Fetch halls
    $hallsRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/halls?lang=ar')
        ->assertStatus(200);

    expect(collect($hallsRes->json('data'))->pluck('id')->all())->toContain($hall1->id, $hall2->id);

    // Fetch hall tables filtered by hall1
    $tablesRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/hall-tables?hall_id='.$hall1->id)
        ->assertStatus(200);

    $tableIds = collect($tablesRes->json('data'))->pluck('id')->all();
    expect($tableIds)->toContain($t1->id);
    expect($tableIds)->not->toContain($t2->id);
});
