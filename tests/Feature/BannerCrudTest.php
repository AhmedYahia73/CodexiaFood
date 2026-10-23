<?php

use App\Models\Admin;
use App\Models\Banner;
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

test('unauthenticated request to admin banners endpoints returns 401', function () {
    $this->getJson('/api/admin/banners')->assertStatus(401);
    $this->postJson('/api/admin/banners', [])->assertStatus(401);
});

test('admin can upload and create a new banner', function () {
    $file = UploadedFile::fake()->image('promo_banner.jpg');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/admin/banners', [
            'image' => $file,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'Banner created successfully.',
        ])
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'image',
                'created_at',
                'updated_at',
            ],
        ]);

    $bannerId = $response->json('data.id');
    $imageUrl = $response->json('data.image');

    expect($imageUrl)->toContain('http')
        ->and($imageUrl)->toContain('storage/banners/');

    $banner = Banner::find($bannerId);
    expect($banner)->not()->toBeNull();

    Storage::disk('public')->assertExists($banner->image);
});

test('admin can list banners with pagination', function () {
    Banner::create(['image' => 'banners/banner1.jpg']);
    Banner::create(['image' => 'banners/banner2.jpg']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/admin/banners?per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'image', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.image'))->toContain(url('storage/banners/'));
});

test('admin can view a single banner by id', function () {
    $banner = Banner::create(['image' => 'banners/single.jpg']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson("/api/admin/banners/{$banner->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $banner->id,
                'image' => url('storage/banners/single.jpg'),
            ],
        ]);
});

test('admin can delete banner and old image file is deleted from storage first', function () {
    $file = UploadedFile::fake()->image('delete_me.png');
    $path = $file->store('banners', 'public');

    $banner = Banner::create(['image' => $path]);

    Storage::disk('public')->assertExists($path);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson("/api/admin/banners/{$banner->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'Banner deleted successfully.',
        ]);

    // Old image is deleted from storage
    Storage::disk('public')->assertMissing($path);

    // Record is removed from DB
    expect(Banner::find($banner->id))->toBeNull();
});

test('admin cannot update banner because no edit route exists', function () {
    $banner = Banner::create(['image' => 'banners/no_edit.jpg']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->putJson("/api/admin/banners/{$banner->id}", [
            'image' => UploadedFile::fake()->image('new.jpg'),
        ]);

    expect(in_array($response->status(), [404, 405]))->toBeTrue();
});

test('public user can fetch banners without authentication token', function () {
    Banner::create(['image' => 'banners/public1.jpg']);
    Banner::create(['image' => 'banners/public2.jpg']);

    $response = $this->getJson('/api/user/banners')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'image', 'created_at'],
            ],
        ]);

    expect($response->json('status'))->toBeTrue()
        ->and($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.image'))->toBe(url('storage/banners/public2.jpg'))
        ->and($response->json('data.1.image'))->toBe(url('storage/banners/public1.jpg'));
});
