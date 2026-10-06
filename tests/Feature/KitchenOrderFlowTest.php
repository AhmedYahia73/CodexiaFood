<?php

use App\Models\Addon;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Kitchen;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderDet;
use App\Models\KitchenOrderItem;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\Product;
use App\Models\Variation;
use App\Services\KitchenDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'Main Branch']);

    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'password' => 'password123',
    ]);
    $this->adminToken = JWTAuth::fromUser($this->admin);

    $this->kitchenA = Kitchen::create([
        'name' => ['ar' => 'مطبخ 1', 'en' => 'Kitchen 1'],
        'user_name' => 'kitchen_1',
        'password' => 'password123',
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);
    $this->kitchenAToken = JWTAuth::fromUser($this->kitchenA);

    $this->kitchenB = Kitchen::create([
        'name' => ['ar' => 'مطبخ 2', 'en' => 'Kitchen 2'],
        'user_name' => 'kitchen_2',
        'password' => 'password123',
        'branch_id' => $this->branch->id,
        'status' => true,
    ]);
    $this->kitchenBToken = JWTAuth::fromUser($this->kitchenB);
});

test('products and addons have kitchen_id relation', function () {
    $product = Product::create([
        'name' => ['ar' => 'بيتزا', 'en' => 'Pizza'],
        'image' => 'products/pizza.png',
        'price' => 100,
        'kitchen_id' => $this->kitchenA->id,
    ]);

    $addon = Addon::create([
        'name' => ['ar' => 'جبنة إضافية', 'en' => 'Extra Cheese'],
        'image' => 'addons/cheese.png',
        'price' => 15,
        'kitchen_id' => $this->kitchenB->id,
    ]);

    expect($product->kitchen)->not->toBeNull()
        ->and($product->kitchen->id)->toBe($this->kitchenA->id)
        ->and($addon->kitchen)->not->toBeNull()
        ->and($addon->kitchen->id)->toBe($this->kitchenB->id);
});

test('admin can fetch unassigned products, kitchen products, and assign products to a kitchen', function () {
    $p1 = Product::create([
        'name' => ['ar' => 'منتج 1', 'en' => 'Product 1'],
        'image' => 'products/p1.png',
        'price' => 50,
        'kitchen_id' => null,
    ]);

    $p2 = Product::create([
        'name' => ['ar' => 'منتج 2', 'en' => 'Product 2'],
        'image' => 'products/p2.png',
        'price' => 70,
        'kitchen_id' => null,
    ]);

    // 1. Unassigned products
    $resUnassigned = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/admin/kitchen-products/unassigned?lang=ar');

    $resUnassigned->assertStatus(200)
        ->assertJsonPath('status', true);
    expect($resUnassigned->json('data'))->toHaveCount(2)
        ->and($resUnassigned->json('data.0.name_ar'))->toBe('منتج 1')
        ->and($resUnassigned->json('data.0.name_en'))->toBe('Product 1');

    // 2. Assign to kitchen A
    $resAssign = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson('/api/admin/kitchen-products/assign', [
            'kitchen_id' => $this->kitchenA->id,
            'product_ids' => [$p1->id, $p2->id],
        ]);

    $resAssign->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('count', 2);

    expect($p1->fresh()->kitchen_id)->toBe($this->kitchenA->id)
        ->and($p2->fresh()->kitchen_id)->toBe($this->kitchenA->id);

    // 3. Get products of kitchen A
    $resKitchenProducts = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson("/api/admin/kitchen-products/{$this->kitchenA->id}?lang=en");

    $resKitchenProducts->assertStatus(200)
        ->assertJsonPath('status', true);
    expect($resKitchenProducts->json('data'))->toHaveCount(2)
        ->and($resKitchenProducts->json('data.0.name'))->toBe('Product 1');
});

test('admin can update order status according to module restrictions', function () {
    // Delivery order
    $deliveryOrder = Order::create([
        'module' => 'delivery',
        'total' => 100,
        'final_price' => 100,
        'status' => 'preparing',
    ]);

    // Valid transitions for delivery: done, out_of_delivery, delivered, return, failed_to_delivered / faild_to_delivered
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$deliveryOrder->id}/status", ['status' => 'out_of_delivery'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'out_of_delivery');

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$deliveryOrder->id}/status", ['status' => 'faild_to_delivered'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'faild_to_delivered');

    // Invalid transition for delivery (pickup is only for takeaway)
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$deliveryOrder->id}/status", ['status' => 'pickup'])
        ->assertStatus(422);

    // Takeaway order
    $takeawayOrder = Order::create([
        'module' => 'takeaway',
        'total' => 50,
        'final_price' => 50,
        'status' => 'preparing',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$takeawayOrder->id}/status", ['status' => 'pickup'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'pickup');

    // Dinein order
    $dineinOrder = Order::create([
        'module' => 'dinein',
        'total' => 80,
        'final_price' => 80,
        'status' => 'pickup',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$dineinOrder->id}/status", ['status' => 'return'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'return');

    // Dinein cannot be set to done or out_of_delivery by admin
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/orders/{$dineinOrder->id}/status", ['status' => 'out_of_delivery'])
        ->assertStatus(422);
});

test('dispatch service splits order items across corresponding kitchens with variations and options', function () {
    $pA = Product::create([
        'name' => ['ar' => 'برجر', 'en' => 'Burger'],
        'image' => 'products/burger.png',
        'price' => 80,
        'kitchen_id' => $this->kitchenA->id,
    ]);
    $variation = Variation::create([
        'name' => ['ar' => 'الحجم', 'en' => 'Size'],
        'product_id' => $pA->id,
    ]);
    $option = Option::create([
        'name' => ['ar' => 'كبير', 'en' => 'Large'],
        'price' => 20,
        'product_id' => $pA->id,
        'variation_id' => $variation->id,
    ]);

    $addonB = Addon::create([
        'name' => ['ar' => 'بطاطس', 'en' => 'Fries'],
        'image' => 'addons/fries.png',
        'price' => 25,
        'kitchen_id' => $this->kitchenB->id,
    ]);

    $order = Order::create([
        'module' => 'delivery',
        'total' => 125,
        'final_price' => 125,
        'status' => 'preparing',
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $pA->id,
        'quantity' => 2,
        'price' => 80,
        'note' => 'بدون طماطم',
    ]);

    $orderVar = OrderPVariation::create([
        'order_product_id' => $orderProduct->id,
        'variation_id' => $variation->id,
    ]);

    OrderPOption::create([
        'order_p_variation_id' => $orderVar->id,
        'option_id' => $option->id,
        'price' => 20,
    ]);

    OrderPAddon::create([
        'order_product_id' => $orderProduct->id,
        'addon_id' => $addonB->id,
        'price' => 25,
    ]);

    app(KitchenDispatchService::class)->dispatchFromOrder($order);

    // Kitchen A should have Burger with options
    $koA = KitchenOrder::where('kitchen_id', $this->kitchenA->id)
        ->where('order_id', $order->id)
        ->first();
    expect($koA)->not->toBeNull()
        ->and($koA->items)->toHaveCount(1)
        ->and($koA->items[0]->notes)->toBe('بدون طماطم')
        ->and($koA->items[0]->details)->toHaveCount(1)
        ->and($koA->items[0]->details[0]->option_ids)->toBe([$option->id]);

    // Kitchen B should have Fries addon
    $koB = KitchenOrder::where('kitchen_id', $this->kitchenB->id)
        ->where('order_id', $order->id)
        ->first();
    expect($koB)->not->toBeNull()
        ->and($koB->items)->toHaveCount(1)
        ->and($koB->items[0]->addon_id)->toBe($addonB->id);
});

test('kitchen can fetch its orders with localized product, addon and option names', function () {
    $product = Product::create([
        'name' => ['ar' => 'شاورما', 'en' => 'Shawarma'],
        'image' => 'products/shawarma.png',
        'price' => 60,
        'kitchen_id' => $this->kitchenA->id,
    ]);
    $var = Variation::create([
        'name' => ['ar' => 'حجم', 'en' => 'Size'],
        'product_id' => $product->id,
    ]);
    $option = Option::create([
        'name' => ['ar' => 'عائلي', 'en' => 'Family'],
        'price' => 40,
        'product_id' => $product->id,
        'variation_id' => $var->id,
    ]);

    $order = Order::create([
        'module' => 'takeaway',
        'total' => 100,
        'final_price' => 100,
        'status' => 'preparing',
    ]);

    $ko = KitchenOrder::create([
        'kitchen_id' => $this->kitchenA->id,
        'order_id' => $order->id,
    ]);

    $item = KitchenOrderItem::create([
        'kitchen_order_id' => $ko->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'notes' => 'حار جدا',
    ]);

    KitchenOrderDet::create([
        'kitchen_order_item_id' => $item->id,
        'option_ids' => [$option->id],
    ]);

    // Request in Arabic
    $resAr = $this->withHeader('Authorization', 'Bearer '.$this->kitchenAToken)
        ->getJson('/api/kitchen/orders?lang=ar');

    $resAr->assertStatus(200)
        ->assertJsonPath('status', true);
    expect($resAr->json('data.0.items.0.product_name'))->toBe('شاورما')
        ->and($resAr->json('data.0.items.0.details.0.option_names.0'))->toBe('عائلي')
        ->and($resAr->json('data.0.items.0.notes'))->toBe('حار جدا');

    // Request in English
    $resEn = $this->withHeader('Authorization', 'Bearer '.$this->kitchenAToken)
        ->getJson('/api/kitchen/orders?lang=en');

    $resEn->assertStatus(200);
    expect($resEn->json('data.0.items.0.product_name'))->toBe('Shawarma')
        ->and($resEn->json('data.0.items.0.details.0.option_names.0'))->toBe('Family');
});

test('kitchen markDone removes its KitchenOrder and only sets order to done when no other kitchens are pending', function () {
    $order = Order::create([
        'module' => 'delivery',
        'total' => 200,
        'final_price' => 200,
        'status' => 'preparing',
    ]);

    $koA = KitchenOrder::create([
        'kitchen_id' => $this->kitchenA->id,
        'order_id' => $order->id,
    ]);

    $koB = KitchenOrder::create([
        'kitchen_id' => $this->kitchenB->id,
        'order_id' => $order->id,
    ]);

    // 1. Kitchen A marks done -> Order should STILL be preparing because Kitchen B is pending
    $resA = $this->withHeader('Authorization', 'Bearer '.$this->kitchenAToken)
        ->putJson("/api/kitchen/orders/{$koA->id}/done");

    $resA->assertStatus(200);
    expect(KitchenOrder::find($koA->id))->toBeNull()
        ->and($order->fresh()->status)->toBe('preparing');

    // 2. Kitchen B marks done -> Now all kitchens are finished, Order status should become done!
    auth('kitchen')->logout();
    JWTAuth::unsetToken();
    Auth::forgetGuards();

    $resB = $this->withHeader('Authorization', 'Bearer '.$this->kitchenBToken)
        ->putJson("/api/kitchen/orders/{$koB->id}/done");

    $resB->assertStatus(200);
    expect(KitchenOrder::find($koB->id))->toBeNull()
        ->and($order->fresh()->status)->toBe('done');
});

test('terminal status updates on Order or OrderCart auto delete all related KitchenOrders', function () {
    $order = Order::create([
        'module' => 'delivery',
        'total' => 100,
        'final_price' => 100,
        'status' => 'preparing',
    ]);

    $ko = KitchenOrder::create([
        'kitchen_id' => $this->kitchenA->id,
        'order_id' => $order->id,
    ]);

    expect(KitchenOrder::where('order_id', $order->id)->count())->toBe(1);

    // Update order status to out_of_delivery
    $order->update(['status' => 'out_of_delivery']);

    // KitchenOrder should be automatically deleted by observer
    expect(KitchenOrder::where('order_id', $order->id)->count())->toBe(0);

    // Test with OrderCart (dinein)
    $cart = OrderCart::create([
        'module' => 'dinein',
        'product_id' => Product::create(['name' => ['ar' => 'سلطة'], 'image' => 'products/salad.png', 'price' => 30])->id,
        'quantity' => 1,
        'status' => 'preparing',
    ]);

    $koCart = KitchenOrder::create([
        'kitchen_id' => $this->kitchenA->id,
        'order_cart_id' => $cart->id,
    ]);

    expect(KitchenOrder::where('order_cart_id', $cart->id)->count())->toBe(1);

    // Update cart status to done
    $cart->update(['status' => 'done']);

    expect(KitchenOrder::where('order_cart_id', $cart->id)->count())->toBe(0);
});

test('admin order store creates order with correct status and dispatches to kitchen', function () {
    $product = Product::create([
        'name' => ['ar' => 'بيتزا مشكل'],
        'image' => 'products/pizza.png',
        'price' => 120,
        'kitchen_id' => $this->kitchenA->id,
    ]);

    // 1. Delivery order -> preparing status & dispatched to kitchen
    $resDelivery = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/orders', [
            'module' => 'delivery',
            'total' => 120,
            'final_price' => 120,
            'products' => [
                [
                    'product_id' => $product->id,
                    'price' => 120,
                    'quantity' => 1,
                    'note' => 'مستعجل',
                ],
            ],
        ]);

    $resDelivery->assertStatus(201)
        ->assertJsonPath('data.status', 'preparing');

    $orderId = $resDelivery->json('data.id');
    $kitchenOrder = KitchenOrder::where('order_id', $orderId)->first();
    expect($kitchenOrder)->not->toBeNull()
        ->and($kitchenOrder->kitchen_id)->toBe($this->kitchenA->id)
        ->and($kitchenOrder->items)->toHaveCount(1)
        ->and($kitchenOrder->items[0]->notes)->toBe('مستعجل');

    // 2. Dinein order -> pickup status
    $resDinein = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/orders', [
            'module' => 'dinein',
            'total' => 120,
            'final_price' => 120,
            'products' => [
                [
                    'product_id' => $product->id,
                    'price' => 120,
                    'quantity' => 1,
                ],
            ],
        ]);

    $resDinein->assertStatus(201)
        ->assertJsonPath('data.status', 'pickup');
});
