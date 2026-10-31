<?php

use App\Models\Admin;
use App\Models\Unit;
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

test('unauthenticated request to unit endpoints returns 401', function () {
    $this->getJson('/api/admin/units')->assertStatus(401);
    $this->getJson('/api/admin/units/select-options')->assertStatus(401);
    $this->postJson('/api/admin/units', [])->assertStatus(401);
});

test('admin can fetch select-options returning units with id, name, and value', function () {
    Unit::create([
        'name' => ['ar' => 'كيلوجرام', 'en' => 'Kilogram'],
        'value' => 1000,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/units/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'units' => [
                    '*' => ['id', 'name', 'value'],
                ],
            ],
        ]);

    expect($response->json('data.units'))->toHaveCount(1)
        ->and((float) $response->json('data.units.0.value'))->toBe(1000.0);
});

test('admin can perform full CRUD on Unit model with default and custom values', function () {
    // 1. Create with default value
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/units', [
            'name' => [
                'ar' => 'قطعة',
                'en' => 'Piece',
            ],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'قطعة')
        ->assertJsonPath('data.name.en', 'Piece')
        ->assertJsonPath('data.value', 1)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'name', 'value', 'created_at', 'updated_at'],
            'select_options' => ['units'],
        ]);

    $unitId = $storeResponse->json('data.id');
    expect($unitId)->not->toBeNull();

    $this->assertDatabaseHas('units', [
        'id' => $unitId,
        'value' => 1,
    ]);

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/units/{$unitId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $unitId)
        ->assertJsonPath('data.name.en', 'Piece')
        ->assertJsonPath('data.value', 1)
        ->assertJsonStructure(['select_options' => ['units']]);

    // 3. Update with custom value
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/units/{$unitId}", [
            'name' => [
                'ar' => 'علبة',
                'en' => 'Box',
            ],
            'value' => 12,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'علبة')
        ->assertJsonPath('data.name.en', 'Box')
        ->assertJsonPath('data.value', 12);

    $this->assertDatabaseHas('units', [
        'id' => $unitId,
        'value' => 12,
    ]);

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/units/{$unitId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('units', ['id' => $unitId]);
});

test('admin can create Unit using simple string name', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/units', [
            'name' => 'Gram',
            'value' => 0.001,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Gram')
        ->assertJsonPath('data.name.ar', 'Gram')
        ->assertJsonPath('data.value', 0.001);
});

test('admin can list units with pagination and search', function () {
    Unit::create([
        'name' => ['ar' => 'لتر', 'en' => 'Liter'],
        'value' => 1,
    ]);

    Unit::create([
        'name' => ['ar' => 'جرام', 'en' => 'Gram'],
        'value' => 0.001,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/units?search=Gram');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'value', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
            'select_options' => ['units'],
        ]);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name.en'))->toBe('Gram');
});
