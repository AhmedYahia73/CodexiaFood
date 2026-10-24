<?php

use App\Models\Admin;
use App\Models\CaptainOrder;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'email' => 'admin@codexa.com',
        'password' => 'secret123',
    ]);

    $this->adminToken = JWTAuth::fromUser($this->admin);
});

test('unauthenticated request to captain orders endpoints returns 401', function () {
    $this->getJson('/api/admin/captain-orders')->assertStatus(401);
    $this->postJson('/api/admin/captain-orders', [])->assertStatus(401);
});

test('admin can list captain orders with pagination', function () {
    CaptainOrder::create([
        'name' => 'Captain One',
        'user_name' => 'captain1',
        'password' => 'password123',
        'status' => true,
    ]);

    CaptainOrder::create([
        'name' => 'Captain Two',
        'user_name' => 'captain2',
        'password' => 'password123',
        'status' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/admin/captain-orders?per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'user_name',
                    'status',
                    'images',
                    'raw_images',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonCount(2, 'data');
});

test('admin can create a captain order with uploaded images', function () {
    $file1 = UploadedFile::fake()->image('license.jpg');
    $file2 = UploadedFile::fake()->image('id_card.png');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/captain-orders', [
            'name' => 'Ahmed Captain',
            'user_name' => 'ahmed_cap',
            'password' => 'password123',
            'status' => 1,
            'images' => [$file1, $file2],
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'Captain order created successfully.',
            'data' => [
                'name' => 'Ahmed Captain',
                'user_name' => 'ahmed_cap',
                'status' => true,
            ],
        ]);

    $captainId = $response->json('data.id');
    $captain = CaptainOrder::findOrFail($captainId);

    expect($captain->name)->toBe('Ahmed Captain')
        ->and($captain->user_name)->toBe('ahmed_cap')
        ->and($captain->status)->toBeTrue()
        ->and($captain->images)->toHaveCount(2);

    foreach ($captain->images as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('admin can view a single captain order', function () {
    $captain = CaptainOrder::create([
        'name' => 'Mohamed Captain',
        'user_name' => 'mohamed_cap',
        'password' => 'password123',
        'status' => true,
        'images' => ['captain_orders/test.jpg'],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/admin/captain-orders/'.$captain->id);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $captain->id,
                'name' => 'Mohamed Captain',
                'user_name' => 'mohamed_cap',
                'status' => true,
            ],
        ]);
});

test('admin can update a captain order with new_images and deleted_images', function () {
    // 1. First upload initial images
    $initialFile1 = UploadedFile::fake()->image('initial1.jpg');
    $initialFile2 = UploadedFile::fake()->image('initial2.jpg');
    $path1 = $initialFile1->store('captain_orders', 'public');
    $path2 = $initialFile2->store('captain_orders', 'public');

    Storage::disk('public')->assertExists($path1);
    Storage::disk('public')->assertExists($path2);

    $captain = CaptainOrder::create([
        'name' => 'Old Name',
        'user_name' => 'old_cap',
        'password' => 'old_pass_123',
        'status' => true,
        'images' => [$path1, $path2],
    ]);

    // 2. Update: delete path1, add new_file
    $newFile = UploadedFile::fake()->image('new_id.jpg');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/captain-orders/'.$captain->id, [
            '_method' => 'PUT',
            'name' => 'Updated Captain Name',
            'status' => 0,
            'deleted_images' => [$path1],
            'new_images' => [$newFile],
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'Captain order updated successfully.',
            'data' => [
                'name' => 'Updated Captain Name',
                'status' => false,
            ],
        ]);

    // Verify disk: path1 must be deleted from server, path2 kept, newFile stored
    Storage::disk('public')->assertMissing($path1);
    Storage::disk('public')->assertExists($path2);

    $captain->refresh();
    expect($captain->name)->toBe('Updated Captain Name')
        ->and($captain->status)->toBeFalse()
        ->and($captain->images)->toHaveCount(2)
        ->and($captain->images)->not->toContain($path1)
        ->and($captain->images)->toContain($path2);

    foreach ($captain->images as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('deleting a captain order deletes its images from server first', function () {
    $file1 = UploadedFile::fake()->image('img1.jpg');
    $file2 = UploadedFile::fake()->image('img2.jpg');
    $path1 = $file1->store('captain_orders', 'public');
    $path2 = $file2->store('captain_orders', 'public');

    $captain = CaptainOrder::create([
        'name' => 'Delete Me',
        'user_name' => 'delete_cap',
        'password' => 'secret123',
        'status' => true,
        'images' => [$path1, $path2],
    ]);

    Storage::disk('public')->assertExists($path1);
    Storage::disk('public')->assertExists($path2);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson('/api/admin/captain-orders/'.$captain->id);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'Captain order deleted successfully.',
        ]);

    // Check images are deleted from disk
    Storage::disk('public')->assertMissing($path1);
    Storage::disk('public')->assertMissing($path2);

    // Check captain is deleted from database
    expect(CaptainOrder::find($captain->id))->toBeNull();
});

test('order can belong to a captain order via captain_order_id', function () {
    $captain = CaptainOrder::create([
        'name' => 'Captain Ali',
        'user_name' => 'ali_cap',
        'password' => 'password123',
        'status' => true,
    ]);

    $order = Order::create([
        'module' => 'delivery',
        'total' => 100,
        'total_tax' => 10,
        'total_discount' => 0,
        'final_price' => 110,
        'captain_order_id' => $captain->id,
    ]);

    expect($order->captain_order_id)->toBe($captain->id)
        ->and($order->captainOrder)->not->toBeNull()
        ->and($order->captainOrder->id)->toBe($captain->id)
        ->and($captain->orders)->toHaveCount(1)
        ->and($captain->orders->first()->id)->toBe($order->id);
});
