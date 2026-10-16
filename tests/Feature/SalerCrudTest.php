<?php

use App\Models\Admin;
use App\Models\Saler;
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

test('unauthenticated request to saler endpoints returns 401', function () {
    $this->getJson('/api/admin/salers')->assertStatus(401);
    $this->getJson('/api/admin/salers/select-options')->assertStatus(401);
    $this->postJson('/api/admin/salers', [])->assertStatus(401);
});

test('admin can fetch select-options returning salers with id, name, phone, and balance', function () {
    Saler::create([
        'name' => 'موزع النيل للأغذية',
        'phone' => '01001112233',
        'email' => 'nile@example.com',
        'balance' => 3500.00,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/salers/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'salers' => [
                    '*' => ['id', 'name', 'phone', 'balance'],
                ],
            ],
        ]);

    expect($response->json('data.salers'))->toHaveCount(1)
        ->and((float) $response->json('data.salers.0.balance'))->toBe(3500.00);
});

test('admin can perform full CRUD on Saler and balance CANNOT be updated via update endpoint', function () {
    // 1. Create (Store) with initial balance
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/salers', [
            'name' => 'شركة الأهرام للتوزيع',
            'phone' => '01223344556',
            'email' => 'ahram@dist.com',
            'balance' => 5000.00,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'شركة الأهرام للتوزيع')
        ->assertJsonPath('data.phone', '01223344556')
        ->assertJsonPath('data.email', 'ahram@dist.com')
        ->assertJsonPath('data.balance', 5000)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'name', 'phone', 'email', 'balance', 'created_at', 'updated_at'],
        ]);

    $salerId = $storeResponse->json('data.id');
    expect($salerId)->not->toBeNull();

    $this->assertDatabaseHas('salers', [
        'id' => $salerId,
        'balance' => 5000,
    ]);

    // 2. Index listing
    $indexResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/salers');

    $indexResponse->assertStatus(200)
        ->assertJsonPath('data.0.id', $salerId);

    // 3. Show
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/salers/{$salerId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $salerId)
        ->assertJsonPath('data.name', 'شركة الأهرام للتوزيع');

    // 4. Update - attempting to modify balance must be ignored
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/salers/{$salerId}", [
            'name' => 'شركة الأهرام للتوزيع والخدمات',
            'phone' => '01555555555',
            'email' => 'new-ahram@dist.com',
            'balance' => 999999.00, // Should be ignored
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'شركة الأهرام للتوزيع والخدمات')
        ->assertJsonPath('data.phone', '01555555555')
        ->assertJsonPath('data.email', 'new-ahram@dist.com')
        ->assertJsonPath('data.balance', 5000); // Balance remains unchanged

    $this->assertDatabaseHas('salers', [
        'id' => $salerId,
        'name' => 'شركة الأهرام للتوزيع والخدمات',
        'balance' => 5000.00,
    ]);

    // 5. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/salers/{$salerId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('salers', [
        'id' => $salerId,
    ]);
});
