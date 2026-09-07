<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Kitchen;
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

test('unauthenticated request to kitchen endpoints returns 401', function () {
    $this->getJson('/api/admin/kitchens')->assertStatus(401);
    $this->getJson('/api/admin/kitchens/select-options')->assertStatus(401);
    $this->postJson('/api/admin/kitchens', [])->assertStatus(401);
});

test('admin can fetch dedicated select-options for kitchens returning branches with id and name', function () {
    Branch::create(['name' => 'Cairo Branch']);
    Branch::create(['name' => 'Alex Branch']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/kitchens/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches' => [
                    '*' => ['id', 'name'],
                ],
            ],
        ]);

    expect($response->json('data.branches'))->toHaveCount(2);
});

test('admin can perform full CRUD on Kitchen model with multilingual name and user_name', function () {
    $branch = Branch::create(['name' => 'Main Branch']);

    // 1. Create (Store)
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/kitchens', [
            'name' => [
                'ar' => 'مطبخ المشويات',
                'en' => 'Grill Kitchen',
            ],
            'user_name' => 'grill_kitchen_01',
            'password' => 'kitchen123',
            'branch_id' => $branch->id,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'مطبخ المشويات')
        ->assertJsonPath('data.name.en', 'Grill Kitchen')
        ->assertJsonPath('data.user_name', 'grill_kitchen_01')
        ->assertJsonPath('data.branch_id', $branch->id)
        ->assertJsonPath('data.branch.id', $branch->id)
        ->assertJsonPath('data.branch.name', 'Main Branch')
        ->assertJsonPath('data.status', true)
        ->assertJsonStructure([
            'select_options' => [
                'branches' => [
                    '*' => ['id', 'name'],
                ],
            ],
        ]);

    $kitchenId = $storeResponse->json('data.id');

    // 2. Read (Show)
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/kitchens/{$kitchenId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $kitchenId)
        ->assertJsonPath('data.name.en', 'Grill Kitchen')
        ->assertJsonPath('data.user_name', 'grill_kitchen_01')
        ->assertJsonStructure(['select_options' => ['branches']]);

    // 3. Update (with password encryption and updated user_name)
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/kitchens/{$kitchenId}", [
            'name' => [
                'ar' => 'مطبخ الحلويات',
                'en' => 'Dessert Kitchen',
            ],
            'user_name' => 'dessert_kitchen_01',
            'password' => 'newpassword456',
            'status' => false,
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name.ar', 'مطبخ الحلويات')
        ->assertJsonPath('data.name.en', 'Dessert Kitchen')
        ->assertJsonPath('data.user_name', 'dessert_kitchen_01')
        ->assertJsonPath('data.status', false);

    // Verify password was hashed/encrypted and works for kitchen login
    $loginResponse = $this->postJson('/api/auth/login', [
        'name' => 'dessert_kitchen_01',
        'password' => 'newpassword456',
        'guard' => 'kitchen',
    ]);

    $loginResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.guard', 'kitchen');

    // 4. Delete (Destroy)
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/kitchens/{$kitchenId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('kitchens', ['id' => $kitchenId]);
});

test('admin can create Kitchen using simple string name', function () {
    $branch = Branch::create(['name' => 'Delta Branch']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/kitchens', [
            'name' => 'Fast Food Kitchen',
            'user_name' => 'fastfood_01',
            'password' => 'kitchen123',
            'branch_id' => $branch->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Fast Food Kitchen')
        ->assertJsonPath('data.name.ar', 'Fast Food Kitchen')
        ->assertJsonPath('data.user_name', 'fastfood_01');
});

test('admin can list kitchens with pagination and select_options', function () {
    $branch = Branch::create(['name' => 'Branch 1']);

    Kitchen::create([
        'name' => ['ar' => 'مطبخ 1', 'en' => 'Kitchen 1'],
        'user_name' => 'kitchen_branch_1',
        'password' => 'kitchen123',
        'branch_id' => $branch->id,
        'status' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/kitchens');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'user_name', 'branch_id', 'branch', 'status', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
            'select_options' => ['branches'],
        ]);
});

test('jwt token expiration is set to 3 days (259200 seconds)', function () {
    $response = $this->postJson('/api/auth/login', [
        'name' => 'Super Admin',
        'password' => 'password123',
        'guard' => 'admin',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.expires_in', 3 * 24 * 60 * 60); // 259200 seconds
});
