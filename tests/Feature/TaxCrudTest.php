<?php

use App\Models\Admin;
use App\Models\Tax;
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

test('unauthenticated request to tax endpoints returns 401', function () {
    $this->getJson('/api/admin/taxes')->assertStatus(401);
    $this->getJson('/api/admin/taxes/select-options')->assertStatus(401);
    $this->postJson('/api/admin/taxes', [])->assertStatus(401);
});

test('admin can fetch select-options returning taxes with id, name, type, amount, and status', function () {
    Tax::create([
        'name' => ['ar' => 'ضريبة القيمة المضافة', 'en' => 'VAT'],
        'type' => 'percentage',
        'amount' => 14.00,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/taxes/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'taxes' => [
                    '*' => ['id', 'name', 'type', 'amount', 'status'],
                ],
            ],
        ]);

    expect($response->json('data.taxes'))->toHaveCount(1)
        ->and((float) $response->json('data.taxes.0.amount'))->toBe(14.00);
});

test('admin can perform full CRUD on Tax model with percentage and value types', function () {
    // 1. Create (Store)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/taxes', [
            'name' => [
                'ar' => 'ضريبة المبيعات',
                'en' => 'Sales Tax',
            ],
            'type' => 'percentage',
            'amount' => 14.00,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'ضريبة المبيعات')
        ->assertJsonPath('data.name.en', 'Sales Tax')
        ->assertJsonPath('data.type', 'percentage')
        ->assertJsonPath('data.amount', 14)
        ->assertJsonPath('data.status', true)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'name', 'type', 'amount', 'status', 'created_at', 'updated_at'],
            'select_options' => ['taxes'],
        ]);

    $taxId = $storeResponse->json('data.id');
    expect($taxId)->not->toBeNull();

    $this->assertDatabaseHas('taxes', [
        'id' => $taxId,
        'type' => 'percentage',
        'amount' => 14.00,
    ]);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/taxes/{$taxId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $taxId)
        ->assertJsonPath('data.name.en', 'Sales Tax')
        ->assertJsonStructure(['select_options' => ['taxes']]);

    // 3. Update
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/taxes/{$taxId}", [
            'name' => [
                'ar' => 'ضريبة خدمة ثابتة',
                'en' => 'Fixed Service Tax',
            ],
            'type' => 'value',
            'amount' => 20.00,
            'status' => false,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'ضريبة خدمة ثابتة')
        ->assertJsonPath('data.type', 'value')
        ->assertJsonPath('data.amount', 20)
        ->assertJsonPath('data.status', false);

    $this->assertDatabaseHas('taxes', [
        'id' => $taxId,
        'type' => 'value',
        'amount' => 20.00,
        'status' => false,
    ]);

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/taxes/{$taxId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('taxes', ['id' => $taxId]);
});

test('admin can create Tax using simple string name', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/taxes', [
            'name' => 'Municipality Tax',
            'type' => 'percentage',
            'amount' => 2.5,
            'status' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Municipality Tax')
        ->assertJsonPath('data.name.ar', 'Municipality Tax')
        ->assertJsonPath('data.amount', 2.5);
});

test('admin can list taxes with pagination and select_options', function () {
    Tax::create([
        'name' => ['ar' => 'ضريبة تجريبية', 'en' => 'Test Tax'],
        'type' => 'percentage',
        'amount' => 10,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/taxes');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'type', 'amount', 'status', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
            'select_options' => ['taxes'],
        ]);
});
