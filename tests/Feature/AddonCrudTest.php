<?php

use App\Models\Addon;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\Kitchen;
use App\Models\Tax;
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
        'password' => 'password123',
    ]);
    $this->adminToken = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        'user_name' => 'branch_user',
        'password' => 'secret123',
    ]);
    $this->branchToken = auth('branch')->tokenById($this->branch->id);

    $this->tax = Tax::create([
        'name' => ['en' => 'VAT 15%', 'ar' => 'ضريبة 15%'],
        'type' => 'percentage',
        'amount' => 15.00,
        'status' => true,
    ]);

    $this->discount = Discount::create([
        'name' => ['en' => 'Discount 10%', 'ar' => 'خصم 10%'],
        'type' => 'percentage',
        'amount' => 10.00,
        'status' => true,
    ]);

    $this->kitchen = Kitchen::create([
        'name' => ['en' => 'Main Kitchen', 'ar' => 'المطبخ الرئيسي'],
        'branch_id' => $this->branch->id,
        'user_name' => 'kitch1',
        'password' => 'secret123',
    ]);
});

test('unauthenticated request to admin and branch addon endpoints returns 401', function () {
    $this->getJson('/api/admin/addons')->assertStatus(401);
    $this->getJson('/api/branch/addons')->assertStatus(401);
});

test('admin can fetch lists and select-options with taxes and discounts', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/admin/addons/lists');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'taxes' => [
                    '*' => ['id', 'name' => ['en', 'ar'], 'name_en', 'name_ar', 'type', 'amount'],
                ],
                'discounts' => [
                    '*' => ['id', 'name' => ['en', 'ar'], 'name_en', 'name_ar', 'type', 'amount'],
                ],
                'kitchens',
            ],
        ]);

    $taxItem = $response->json('data.taxes.0');
    expect($taxItem['id'])->toBe($this->tax->id);
    expect($taxItem['name_en'])->toBe('VAT 15%');
    expect($taxItem['name_ar'])->toBe('ضريبة 15%');

    $discountItem = $response->json('data.discounts.0');
    expect($discountItem['id'])->toBe($this->discount->id);
    expect($discountItem['name_en'])->toBe('Discount 10%');
    expect($discountItem['name_ar'])->toBe('خصم 10%');
});

test('branch can fetch lists with taxes and discounts in a single API', function () {
    $branchRes = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->getJson('/api/branch/addons/lists');

    $branchRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.taxes.0.id', $this->tax->id)
        ->assertJsonPath('data.taxes.0.name_en', 'VAT 15%')
        ->assertJsonPath('data.taxes.0.name_ar', 'ضريبة 15%')
        ->assertJsonPath('data.discounts.0.id', $this->discount->id)
        ->assertJsonPath('data.discounts.0.name_en', 'Discount 10%')
        ->assertJsonPath('data.discounts.0.name_ar', 'خصم 10%');
});

test('admin can perform full CRUD on addon including image upload, update replacement, and delete removal', function () {
    $firstImage = UploadedFile::fake()->image('addon_initial.png');

    // 1. Create (Add)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/addons', [
            'name' => [
                'en' => 'Extra Cheese',
                'ar' => 'جبنة إضافية',
            ],
            'price' => 15.50,
            'image' => $firstImage,
            'tax_id' => $this->tax->id,
            'discount_id' => $this->discount->id,
            'kitchen_id' => $this->kitchen->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.en', 'Extra Cheese')
        ->assertJsonPath('data.name.ar', 'جبنة إضافية');

    $addonId = $storeResponse->json('data.id');
    $addon = Addon::findOrFail($addonId);
    $initialPath = $addon->image;

    expect($initialPath)->not->toBeNull();
    Storage::disk('public')->assertExists($initialPath);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson("/api/admin/addons/{$addonId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $addonId)
        ->assertJsonPath('data.tax.id', $this->tax->id)
        ->assertJsonPath('data.discount.id', $this->discount->id);

    // 3. Update with new image
    $newImage = UploadedFile::fake()->image('addon_updated.png');

    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson("/api/admin/addons/{$addonId}", [
            'name' => [
                'en' => 'Extra Mozzarella Cheese',
                'ar' => 'جبنة موزاريلا إضافية',
            ],
            'price' => 20.00,
            'image' => $newImage,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $addon->refresh();
    $updatedPath = $addon->image;

    expect($updatedPath)->not->toBe($initialPath);
    // Old image deleted from disk
    Storage::disk('public')->assertMissing($initialPath);
    // New image exists on disk
    Storage::disk('public')->assertExists($updatedPath);

    // 4. Delete
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson("/api/admin/addons/{$addonId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    // Deleted from DB
    $this->assertDatabaseMissing('addons', ['id' => $addonId]);
    // Image removed from disk
    Storage::disk('public')->assertMissing($updatedPath);
});

test('branch can perform full CRUD on addon including image upload, update replacement, and delete removal', function () {
    $firstImage = UploadedFile::fake()->image('branch_addon.png');

    // 1. Branch creates addon
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->postJson('/api/branch/addons', [
            'name' => [
                'en' => 'Garlic Sauce',
                'ar' => 'صلصة ثوم',
            ],
            'price' => 8.00,
            'image' => $firstImage,
            'tax_id' => $this->tax->id,
            'kitchen_id' => $this->kitchen->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.en', 'Garlic Sauce')
        ->assertJsonPath('data.name.ar', 'صلصة ثوم');

    $addonId = $storeResponse->json('data.id');
    $addon = Addon::findOrFail($addonId);
    $initialPath = $addon->image;
    expect($initialPath)->not->toBeNull();
    Storage::disk('public')->assertExists($initialPath);

    // 2. Branch index
    $indexRes = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->getJson('/api/branch/addons');

    $indexRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data' => [['id', 'name', 'price', 'image']]]);

    // 3. Branch show
    $showRes = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->getJson("/api/branch/addons/{$addonId}");

    $showRes->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $addonId);

    // 4. Branch updates addon with new image -> old image deleted, new uploaded
    $newImage = UploadedFile::fake()->image('branch_addon_new.png');
    $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->postJson("/api/branch/addons/{$addonId}", [
            'name' => [
                'en' => 'Spicy Garlic Sauce',
                'ar' => 'صلصة ثوم حارة',
            ],
            'price' => 10.00,
            'image' => $newImage,
        ]);

    $updateRes->assertStatus(200);
    $addon->refresh();
    $newPath = $addon->image;
    expect($newPath)->not->toBe($initialPath);
    Storage::disk('public')->assertMissing($initialPath);
    Storage::disk('public')->assertExists($newPath);

    // 5. Branch deletes addon -> image deleted from storage, record removed from DB
    $deleteRes = $this->withHeader('Authorization', 'Bearer '.$this->branchToken)
        ->deleteJson("/api/branch/addons/{$addonId}");

    $deleteRes->assertStatus(200);
    $this->assertDatabaseMissing('addons', ['id' => $addonId]);
    Storage::disk('public')->assertMissing($newPath);
});
