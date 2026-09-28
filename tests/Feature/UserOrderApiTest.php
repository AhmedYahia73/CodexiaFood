<?php

use App\Models\Addon;
use App\Models\Branch;
use App\Models\BusinessSetup;
use App\Models\Discount;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 1. Branch in Maadi (Lat: 29.9602, Lng: 31.2569)
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'status' => true,
        'location' => [
            ['lat' => 29.9600, 'lng' => 31.2560],
            ['lat' => 29.9610, 'lng' => 31.2560],
            ['lat' => 29.9610, 'lng' => 31.2570],
            ['lat' => 29.9600, 'lng' => 31.2570],
        ],
    ]);

    // 2. BusinessSetup with 5 km cover
    $this->businessSetup = BusinessSetup::create([
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => 'business/logo.png',
        'description' => 'أفضل تجربة طعام',
        'branch_cover' => 5.00,
    ]);

    // 3. Discount & Tax
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

    // 4. Product, Variation, Option, Addon
    $this->product = Product::create([
        'name' => ['ar' => 'برجر لحم فاخر', 'en' => 'Gourmet Beef Burger'],
        'price' => 120.00,
        'image' => 'products/burger.jpg',
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
        'name' => ['ar' => 'بطاطس مقلية', 'en' => 'French Fries'],
        'image' => 'addons/fries.jpg',
        'price' => 25.00,
    ]);

    $this->uuId = 'client-device-uuid-999';
});

test('business setup default branch_cover is 5 km', function () {
    $setup = BusinessSetup::create([
        'name' => 'فرع تجريبي',
        'phone' => '01011112222',
        'face' => 'https://facebook.com/test',
        'instagram' => 'https://instagram.com/test',
        'whats' => '01011112222',
        'logo' => 'business/logo2.png',
        'description' => 'وصف تجريبي',
    ]);

    expect((float) $setup->branch_cover)->toBe(5.00);
});

test('checkout validates all required keys for documentation and swagger', function () {
    $this->postJson('/api/user/orders/checkout', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'uu_id',
            'lat',
            'lng',
            'address',
            'phone',
            'name',
        ]);
});

test('user checkout succeeds when location is within branch_cover and assigns nearest branch', function () {
    // 1. Add item to user cart
    $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId,
        'product_id' => $this->product->id,
        'quantity' => 2,
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
    ])->assertStatus(201);

    expect(OrderCart::where('uu_id', $this->uuId)->count())->toBe(1);

    // 2. Checkout at location close to Maadi branch (~1 km away)
    $payload = [
        'uu_id' => $this->uuId,
        'lat' => 29.9650,
        'lng' => 31.2580,
        'address' => 'شارع 9، المعادي، القاهرة',
        'phone' => '01012345678',
        'name' => 'أحمد يحيى',
        'note' => 'بدون شطة',
    ];

    $response = $this->postJson('/api/user/orders/checkout', $payload)
        ->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح من السلة',
        ]);

    $orderId = $response->json('data.id');
    $order = Order::find($orderId);

    expect($order)->not->toBeNull();
    expect($order->branch_id)->toBe($this->branch->id);
    expect($order->shift_id)->toBeNull();
    expect($order->cashier_id)->toBeNull();
    expect($order->cashier_man_id)->toBeNull();
    expect($order->hall_table_id)->toBeNull();
    expect($order->is_pos)->toBeFalse();
    expect($order->address)->toBe('شارع 9، المعادي، القاهرة');
    expect((float) $order->lat)->toBe(29.9650);
    expect((float) $order->lng)->toBe(31.2580);
    expect($order->phone)->toBe('01012345678');
    expect($order->name)->toBe('أحمد يحيى');

    // Cart must be cleared
    expect(OrderCart::where('uu_id', $this->uuId)->count())->toBe(0);
});

test('user checkout fails with 422 when location is outside branch_cover', function () {
    // 1. Add item to user cart
    $this->postJson('/api/user/cart', [
        'uu_id' => $this->uuId,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ])->assertStatus(201);

    // 2. Checkout from Alexandria (~180 km away from Maadi, while cover is 5 km)
    $payload = [
        'uu_id' => $this->uuId,
        'lat' => 31.2001,
        'lng' => 29.9187,
        'address' => 'الإسكندرية، محطة الرمل',
        'phone' => '01012345678',
        'name' => 'محمود علي',
    ];

    $response = $this->postJson('/api/user/orders/checkout', $payload)
        ->assertStatus(422)
        ->assertJson([
            'status' => false,
        ]);

    expect($response->json('distance'))->toBeGreaterThan(5);
    expect((float) $response->json('max_cover'))->toBe(5.00);

    // Cart is preserved
    expect(OrderCart::where('uu_id', $this->uuId)->count())->toBe(1);
});

test('user checkout fails with 400 when cart is empty', function () {
    $payload = [
        'uu_id' => 'empty-cart-uuid',
        'lat' => 29.9605,
        'lng' => 31.2565,
        'address' => 'شارع النصر، المعادي',
        'phone' => '01012345678',
        'name' => 'كريم سالم',
    ];

    $this->postJson('/api/user/orders/checkout', $payload)
        ->assertStatus(400)
        ->assertJson([
            'status' => false,
            'message' => 'السلة فارغة، يرجى إضافة منتجات إلى السلة أولاً',
        ]);
});

test('user can view order details', function () {
    $order = Order::create([
        'branch_id' => $this->branch->id,
        'module' => 'delivery',
        'address' => 'المعادي',
        'lat' => 29.9600,
        'lng' => 31.2560,
        'phone' => '01012345678',
        'name' => 'علي حسن',
        'is_pos' => false,
        'total' => 100,
        'total_discount' => 10,
        'total_tax' => 12.6,
        'final_price' => 102.6,
    ]);

    $this->getJson('/api/user/orders/'.$order->id)
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $order->id,
                'branch_id' => $this->branch->id,
                'address' => 'المعادي',
            ],
        ]);
});
