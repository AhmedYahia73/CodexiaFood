<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Option;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
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
        'name' => 'T10',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
        'qr' => 'qrcodes/tables/table_10.svg',
    ]);

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
        'status' => true,
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
});

test('public user can fetch parent and sub categories without auth', function () {
    $parentRes = $this->getJson('/api/table/categories/parents?lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect(collect($parentRes->json('data'))->pluck('id')->all())->toContain($this->parentCat->id);
    expect($parentRes->json('data.0.name'))->toBe('وجبات رئيسية');

    $subRes = $this->getJson('/api/table/categories/sub?category_id='.$this->parentCat->id.'&lang=en')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect(collect($subRes->json('data'))->pluck('id')->all())->toContain($this->subCat->id);
    expect($subRes->json('data.0.name'))->toBe('Burgers');
});

test('public user can fetch products with calculated prices and filter by category without auth', function () {
    $res = $this->getJson('/api/table/products?category_id='.$this->parentCat->id.'&lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    $productData = collect($res->json('data'))->firstWhere('id', $this->product->id);
    expect($productData)->not->toBeNull();
    expect($productData['name'])->toBe('برجر لحم');
    // price = 100, discount 10% = 10, tax 14% on (100-10)=90 => 12.6, final_price = 102.6
    expect($productData['price'])->toEqual(100);
    expect($productData['discount_val'])->toEqual(10);
    expect($productData['tax_val'])->toEqual(12.6);
    expect($productData['final_price'])->toEqual(102.6);
});

test('public user can fetch product details with variations and options without auth', function () {
    $res = $this->getJson('/api/table/products/'.$this->product->id.'?lang=en')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'Beef Burger')
        ->assertJsonPath('data.variations.0.name', 'Size')
        ->assertJsonPath('data.variations.0.options.0.name', 'Double');
});

test('public user can fetch addons without auth', function () {
    $res = $this->getJson('/api/table/addons?lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    $addonData = collect($res->json('data'))->firstWhere('id', $this->addon->id);
    expect($addonData)->not->toBeNull();
    expect($addonData['name'])->toBe('بطاطس');
});

test('public user can fetch table info and scanned qr link without auth', function () {
    $res = $this->getJson('/api/tableOrder/'.$this->table->id.'?lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'T10')
        ->assertJsonPath('data.branch.name', 'فرع المعادي')
        ->assertJsonPath('data.hall.name', 'الصالة الرئيسية');

    expect($res->json('data.qr'))->toContain('/storage/qrcodes/tables/table_10.svg');
});

test('order cart can store table_order module with hall_table_id without cashier', function () {
    $cart = OrderCart::create([
        'module' => 'table_order',
        'product_id' => $this->product->id,
        'branch_id' => $this->branch->id,
        'hall_table_id' => $this->table->id,
        'cashier_id' => null,
        'quantity' => 2,
        'notes' => 'بدون بصل',
    ]);

    expect($cart->module)->toBe('table_order');
    expect($cart->hall_table_id)->toBe($this->table->id);
    expect($cart->cashier_id)->toBeNull();
    expect($cart->hallTable->name)->toBe('T10');
});
