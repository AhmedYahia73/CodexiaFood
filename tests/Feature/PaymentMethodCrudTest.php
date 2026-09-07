<?php

use App\Models\Admin;
use App\Models\PaymentMethod;
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

test('unauthenticated request to payment-method endpoints returns 401', function () {
    $this->getJson('/api/admin/payment-methods')->assertStatus(401);
    $this->getJson('/api/admin/payment-methods/select-options')->assertStatus(401);
    $this->postJson('/api/admin/payment-methods', [])->assertStatus(401);
});

test('admin can fetch select-options returning payment methods with id, name, icon, and status', function () {
    PaymentMethod::create([
        'name' => ['ar' => 'كاش', 'en' => 'Cash'],
        'icon' => 'payment_methods/cash.png',
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/payment-methods/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'payment_methods' => [
                    '*' => ['id', 'name', 'icon', 'status'],
                ],
            ],
        ]);

    expect($response->json('data.payment_methods'))->toHaveCount(1);
});

test('admin can perform full CRUD on PaymentMethod with image upload for icon', function () {
    Storage::fake('public');

    $iconImage = UploadedFile::fake()->image('credit_card.png');

    // 1. Create (Store) with image upload
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/payment-methods', [
            'name' => [
                'ar' => 'بطاقة ائتمانية',
                'en' => 'Credit Card',
            ],
            'description' => [
                'ar' => 'فيزا أو ماستركارد',
                'en' => 'Visa or MasterCard',
            ],
            'icon' => $iconImage,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'بطاقة ائتمانية')
        ->assertJsonPath('data.name.en', 'Credit Card')
        ->assertJsonPath('data.description.ar', 'فيزا أو ماستركارد')
        ->assertJsonPath('data.status', true)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'name',
                'description',
                'icon',
                'status',
                'created_at',
                'updated_at',
            ],
            'select_options' => ['payment_methods'],
        ]);

    $paymentMethodId = $storeResponse->json('data.id');
    $createdPaymentMethod = PaymentMethod::findOrFail($paymentMethodId);

    expect($createdPaymentMethod->icon)->not->toBeNull();
    Storage::disk('public')->assertExists($createdPaymentMethod->icon);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/payment-methods/{$paymentMethodId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $paymentMethodId)
        ->assertJsonPath('data.name.en', 'Credit Card')
        ->assertJsonStructure(['select_options' => ['payment_methods']]);

    // 3. Update with new image upload
    $oldIconPath = $createdPaymentMethod->icon;
    $newIconImage = UploadedFile::fake()->image('updated_card.png');

    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/payment-methods/{$paymentMethodId}", [
            'name' => [
                'ar' => 'بطاقة بنكية ذكية',
                'en' => 'Smart Bank Card',
            ],
            'icon' => $newIconImage,
            'status' => false,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'بطاقة بنكية ذكية')
        ->assertJsonPath('data.status', false);

    $updatedPaymentMethod = PaymentMethod::findOrFail($paymentMethodId);
    $newIconPath = $updatedPaymentMethod->icon;

    expect($newIconPath)->not->toBe($oldIconPath);
    Storage::disk('public')->assertMissing($oldIconPath);
    Storage::disk('public')->assertExists($newIconPath);

    // 4. Delete (Destroy) and verify file cleanup
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/payment-methods/{$paymentMethodId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('payment_methods', ['id' => $paymentMethodId]);
    Storage::disk('public')->assertMissing($newIconPath);
});

test('admin can create PaymentMethod using simple string name and icon path', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/payment-methods', [
            'name' => 'Vodafone Cash',
            'description' => 'Mobile cash wallet payment',
            'icon' => 'payment_methods/vodafone.png',
            'status' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Vodafone Cash')
        ->assertJsonPath('data.name.ar', 'Vodafone Cash')
        ->assertJsonPath('data.description.en', 'Mobile cash wallet payment')
        ->assertJsonPath('data.icon', url('storage/payment_methods/vodafone.png'));
});

test('admin can list payment methods with pagination and select_options', function () {
    PaymentMethod::create([
        'name' => ['ar' => 'كاش تجريبي', 'en' => 'Test Cash'],
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/payment-methods');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'icon',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
            'select_options' => ['payment_methods'],
        ]);
});
