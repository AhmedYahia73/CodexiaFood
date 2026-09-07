<?php

use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('unauthenticated request to supplier endpoints returns 401', function () {
    $this->getJson('/api/admin/suppliers')->assertStatus(401);
    $this->getJson('/api/admin/suppliers/select-options')->assertStatus(401);
    $this->postJson('/api/admin/suppliers', [])->assertStatus(401);
});

test('admin can fetch select-options returning suppliers with id, name, phone, and balance', function () {
    Supplier::create([
        'name' => 'شركة الفا للإنتاج',
        'phone' => '01001112233',
        'email' => 'alpha@example.com',
        'balance' => 2500.00,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/suppliers/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'suppliers' => [
                    '*' => ['id', 'name', 'phone', 'balance'],
                ],
            ],
        ]);

    expect($response->json('data.suppliers'))->toHaveCount(1)
        ->and((float) $response->json('data.suppliers.0.balance'))->toBe(2500.00);
});

test('admin can perform full CRUD on Supplier and balance CANNOT be updated via update endpoint', function () {
    // 1. Create (Store) with initial balance
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/suppliers', [
            'name' => 'مزارع النور الزراعية',
            'phone' => '01223344556',
            'email' => 'alnoor@farms.com',
            'balance' => 7500.50,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'مزارع النور الزراعية')
        ->assertJsonPath('data.phone', '01223344556')
        ->assertJsonPath('data.email', 'alnoor@farms.com')
        ->assertJsonPath('data.balance', 7500.50)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'name', 'phone', 'email', 'balance', 'created_at', 'updated_at'],
            'select_options' => ['suppliers'],
        ]);

    $supplierId = $storeResponse->json('data.id');
    expect($supplierId)->not->toBeNull();

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplierId,
        'balance' => 7500.50,
    ]);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/suppliers/{$supplierId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $supplierId)
        ->assertJsonPath('data.balance', 7500.50)
        ->assertJsonStructure(['select_options' => ['suppliers']]);

    // 3. Update (CRITICAL: Try to change balance to 99999.99 along with name and phone)
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/suppliers/{$supplierId}", [
            'name' => 'شركة مزارع النور الحديثة',
            'phone' => '01555555555',
            'email' => 'new_email@farms.com',
            'balance' => 99999.99, // Should be IGNORED completely
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'شركة مزارع النور الحديثة')
        ->assertJsonPath('data.phone', '01555555555')
        ->assertJsonPath('data.email', 'new_email@farms.com')
        ->assertJsonPath('data.balance', 7500.50); // Balance unchanged!

    // Verify database record has original balance unchanged
    $freshSupplier = Supplier::findOrFail($supplierId);
    expect((float) $freshSupplier->balance)->toBe(7500.50)
        ->and($freshSupplier->name)->toBe('شركة مزارع النور الحديثة')
        ->and($freshSupplier->phone)->toBe('01555555555');

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/suppliers/{$supplierId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('suppliers', ['id' => $supplierId]);
});

test('admin can create Supplier using multilingual name array', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/suppliers', [
            'name' => [
                'ar' => 'شركة الألبان المتحدة',
                'en' => 'United Dairy Co.',
            ],
            'phone' => '01009988776',
            'balance' => 1000,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'شركة الألبان المتحدة')
        ->assertJsonPath('data.balance', 1000);
});

test('admin can list suppliers with pagination and select_options', function () {
    Supplier::create([
        'name' => 'مورد رقم 1',
        'balance' => 300,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/suppliers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'phone', 'email', 'balance', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
            'select_options' => ['suppliers'],
        ]);
});
