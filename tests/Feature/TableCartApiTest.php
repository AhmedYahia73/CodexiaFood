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
        'name' => ['ar' => 'فرع التجمع', 'en' => 'Tagamoa Branch'],
        'status' => true,
    ]);

    $this->hall = Hall::create([
        'name' => ['ar' => 'الصالة الرئيسية', 'en' => 'Main Hall'],
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);

    $this->table = HallTable::create([
        'name' => 'T-5',
        'branch_id' => $this->branch->id,
        'hall_id' => $this->hall->id,
        'status' => true,
    ]);

    $this->category = Category::create([
        'name' => ['ar' => 'برجر', 'en' => 'Burgers'],
        'image' => 'categories/burger.jpg',
        'type' => 'product',
        'status' => true,
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
        'name' => ['ar' => 'برجر دجاج', 'en' => 'Chicken Burger'],
        'image' => 'products/chicken.jpg',
        'category_id' => $this->category->id,
        'price' => 100.00,
        'discount_id' => $this->discount->id,
        'tax_id' => $this->tax->id,
        'stock' => 30,
    ]);

    $this->variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $this->product->id,
        'status' => true,
        'required' => true,
    ]);

    $this->option = Option::create([
        'name' => ['ar' => 'كبير', 'en' => 'Large'],
        'product_id' => $this->product->id,
        'variation_id' => $this->variation->id,
        'price' => 20.00,
        'status' => true,
    ]);

    $this->addon = Addon::create([
        'name' => ['ar' => 'صوص خاص', 'en' => 'Special Sauce'],
        'image' => 'addons/sauce.jpg',
        'price' => 10.00,
    ]);
});

test('public user can add product to table cart without auth, auto dinein module, auto branch, and no cashier', function () {
    $response = $this->postJson('/api/table/cart?lang=ar', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'notes' => 'بدون مايونيز',
        'variations' => [
            [
                'variation_id' => $this->variation->id,
                'option_ids' => [$this->option->id],
            ],
        ],
        'addons' => [
            [
                'addon_id' => $this->addon->id,
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.product.name', 'برجر دجاج');

    $cartId = $response->json('data.id');
    $cart = OrderCart::findOrFail($cartId);

    expect($cart->module)->toBe('dinein');
    expect($cart->hall_table_id)->toBe($this->table->id);
    expect($cart->branch_id)->toBe($this->branch->id);
    expect($cart->cashier_id)->toBeNull();
    expect($cart->cashier_man_id)->toBeNull();
    expect($cart->quantity)->toBe(2);
    expect($cart->notes)->toBe('بدون مايونيز');
});

test('public user can fetch cart items and grand totals for a table', function () {
    // Add item 1
    $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    // Fetch index
    $response = $this->getJson('/api/table/cart?table_id='.$this->table->id)
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data',
            'grand_totals' => [
                'grand_total_price',
                'grand_total_discount',
                'grand_total_tax',
                'grand_final_price',
            ],
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('grand_totals.grand_final_price'))->toBeGreaterThan(0);
});

test('cart index returns 422 when table_id is missing', function () {
    $this->getJson('/api/table/cart')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['table_id']);
});

test('public user can view, update and delete a cart item', function () {
    $storeRes = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    $cartId = $storeRes->json('data.id');

    // Show
    $this->getJson("/api/table/cart/{$cartId}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $cartId);

    // Update
    $updateRes = $this->putJson("/api/table/cart/{$cartId}", [
        'quantity' => 3,
        'notes' => 'ملاحظة محدثة',
    ])->assertStatus(200)
        ->assertJsonPath('data.quantity', 3);

    expect(OrderCart::find($cartId)->quantity)->toBe(3);

    // Delete
    $this->deleteJson("/api/table/cart/{$cartId}")
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect(OrderCart::find($cartId))->toBeNull();
});

test('public user can clear table cart', function () {
    $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ])->assertStatus(201);

    expect(OrderCart::where('hall_table_id', $this->table->id)->count())->toBe(2);

    $this->deleteJson('/api/table/cart/clear', [
        'table_id' => $this->table->id,
    ])->assertStatus(200)
        ->assertJsonPath('status', true);

    expect(OrderCart::where('hall_table_id', $this->table->id)->count())->toBe(0);
});

test('branch geofence allows request when user is inside perimeter', function () {
    $this->branch->update([
        'location' => [
            ['lat' => 30.00, 'lng' => 31.00],
            ['lat' => 30.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 31.00],
        ],
    ]);

    // Inside coordinates via body
    $res = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'lat' => 30.50,
        'lng' => 31.50,
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('status', true);

    $cartId = $res->json('data.id');

    // Inside coordinates via query in index
    $this->getJson('/api/table/cart?table_id='.$this->table->id.'&lat=30.50&lng=31.50')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    // Inside coordinates via headers in show
    $this->withHeaders([
        'X-Lat' => '30.50',
        'X-Lng' => '31.50',
    ])->getJson("/api/table/cart/{$cartId}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $cartId);

    // Inside coordinates via body in update
    $this->putJson("/api/table/cart/{$cartId}", [
        'quantity' => 4,
        'lat' => 30.50,
        'lng' => 31.50,
    ])->assertStatus(200)
        ->assertJsonPath('data.quantity', 4);

    // Inside coordinates via headers in destroy
    $this->withHeaders([
        'X-Lat' => '30.50',
        'X-Lng' => '31.50',
    ])->deleteJson("/api/table/cart/{$cartId}")
        ->assertStatus(200)
        ->assertJsonPath('status', true);
});

test('branch geofence blocks request with 403 when user coordinates are outside perimeter', function () {
    $this->branch->update([
        'location' => [
            ['lat' => 30.00, 'lng' => 31.00],
            ['lat' => 30.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 31.00],
        ],
    ]);

    // Outside coordinates
    $res = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'lat' => 20.00,
        'lng' => 20.00,
    ]);

    $res->assertStatus(403)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'أنت خارج النطاق الجغرافي المسموح به لهذا الفرع');
});

test('branch geofence blocks request with 403 when user coordinates are missing', function () {
    $this->branch->update([
        'location' => [
            ['lat' => 30.00, 'lng' => 31.00],
            ['lat' => 30.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 31.00],
        ],
    ]);

    // No coordinates sent
    $res = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);

    $res->assertStatus(403)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'يرجى تحديد الموقع الجغرافي الخاص بك (lat, lng)');
});

test('branch geofence works with complex polygon from real-world coordinates', function () {
    $this->branch->update([
        'location' => [
            ['lat' => 34.087037887904366, 'lng' => 31.51500799509473],
            ['lat' => 33.649174465794, 'lng' => 22.55016424509473],
            ['lat' => 25.71853395873908, 'lng' => 20.79235174509473],
            ['lat' => 18.72718813381065, 'lng' => 27.47203924509473],
            ['lat' => 19.225869787807213, 'lng' => 42.589226745094734],
        ],
    ]);

    // Point inside polygon
    $insideRes = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'lat' => 26.0,
        'lng' => 28.0,
    ]);

    $insideRes->assertStatus(201)
        ->assertJsonPath('status', true);

    // Point far outside polygon
    $outsideRes = $this->postJson('/api/table/cart', [
        'table_id' => $this->table->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'lat' => 50.0,
        'lng' => 10.0,
    ]);

    $outsideRes->assertStatus(403)
        ->assertJsonPath('message', 'أنت خارج النطاق الجغرافي المسموح به لهذا الفرع');
});

test('branch geofence applies to clear endpoint', function () {
    $this->branch->update([
        'location' => [
            ['lat' => 30.00, 'lng' => 31.00],
            ['lat' => 30.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 32.00],
            ['lat' => 31.00, 'lng' => 31.00],
        ],
    ]);

    // Clear without coords => 403
    $this->deleteJson('/api/table/cart/clear', [
        'table_id' => $this->table->id,
    ])->assertStatus(403)
        ->assertJsonPath('message', 'يرجى تحديد الموقع الجغرافي الخاص بك (lat, lng)');

    // Clear with outside coords => 403
    $this->deleteJson('/api/table/cart/clear', [
        'table_id' => $this->table->id,
        'lat' => 10.0,
        'lng' => 10.0,
    ])->assertStatus(403)
        ->assertJsonPath('message', 'أنت خارج النطاق الجغرافي المسموح به لهذا الفرع');

    // Clear with inside coords => 200
    $this->deleteJson('/api/table/cart/clear', [
        'table_id' => $this->table->id,
        'lat' => 30.5,
        'lng' => 31.5,
    ])->assertStatus(200)
        ->assertJsonPath('status', true);
});
