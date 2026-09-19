<?php

use App\Models\Admin;
use App\Models\BusinessSetup;
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

test('unauthenticated request to business setup endpoints returns 401', function () {
    $this->getJson('/api/admin/business-setup')->assertStatus(401);
    $this->postJson('/api/admin/business-setup', [])->assertStatus(401);
    $this->putJson('/api/admin/business-setup', [])->assertStatus(401);
});

test('admin can fetch business setup when table is empty and receives null data', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/business-setup');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data', null);
});

test('validation fails when any required column is missing', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name',
            'phone',
            'face',
            'instagram',
            'whats',
            'logo',
            'description',
        ]);
});

test('admin can add business setup if not exist via update endpoint', function () {
    Storage::fake('public');

    $logoFile = UploadedFile::fake()->image('restaurant_logo.png');

    $payload = [
        'name' => 'مطعم كودكسا',
        'phone' => '01012345678',
        'face' => 'https://facebook.com/codexa',
        'instagram' => 'https://instagram.com/codexa',
        'whats' => '01012345678',
        'logo' => $logoFile,
        'description' => 'أفضل تجربة طعام لجميع العائلة',
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'مطعم كودكسا')
        ->assertJsonPath('data.phone', '01012345678')
        ->assertJsonPath('data.face', 'https://facebook.com/codexa')
        ->assertJsonPath('data.instagram', 'https://instagram.com/codexa')
        ->assertJsonPath('data.whats', '01012345678')
        ->assertJsonPath('data.description', 'أفضل تجربة طعام لجميع العائلة');

    $this->assertDatabaseCount('business_setups', 1);

    $setup = BusinessSetup::first();
    expect($setup)->not->toBeNull();
    Storage::disk('public')->assertExists($setup->logo);
});

test('index endpoint displays the first record', function () {
    $setup = BusinessSetup::create([
        'name' => 'مطعم كودكسا الأصلي',
        'phone' => '01122334455',
        'face' => 'https://facebook.com/codexaoriginal',
        'instagram' => 'https://instagram.com/codexaoriginal',
        'whats' => '01122334455',
        'logo' => 'business_setup/codexa.png',
        'description' => 'وصف المطعم التجريبي',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/business-setup');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $setup->id)
        ->assertJsonPath('data.name', 'مطعم كودكسا الأصلي')
        ->assertJsonPath('data.phone', '01122334455')
        ->assertJsonPath('data.face', 'https://facebook.com/codexaoriginal')
        ->assertJsonPath('data.instagram', 'https://instagram.com/codexaoriginal')
        ->assertJsonPath('data.whats', '01122334455')
        ->assertJsonPath('data.logo', url('storage/business_setup/codexa.png'))
        ->assertJsonPath('data.description', 'وصف المطعم التجريبي');
});

test('admin can update business setup if record already exists without creating new record', function () {
    Storage::fake('public');

    $firstLogo = UploadedFile::fake()->image('first_logo.png');

    // 1. Initial creation
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم البداية',
            'phone' => '01000000001',
            'face' => 'https://facebook.com/start',
            'instagram' => 'https://instagram.com/start',
            'whats' => '01000000001',
            'logo' => $firstLogo,
            'description' => 'وصف البداية',
        ])->assertStatus(200);

    $this->assertDatabaseCount('business_setups', 1);
    $initialSetup = BusinessSetup::first();
    $oldLogoPath = $initialSetup->logo;
    Storage::disk('public')->assertExists($oldLogoPath);

    // 2. Update existing record with a new logo
    $secondLogo = UploadedFile::fake()->image('second_logo.png');

    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم كودكسا المحدث',
            'phone' => '01099999999',
            'face' => 'https://facebook.com/updated',
            'instagram' => 'https://instagram.com/updated',
            'whats' => '01099999999',
            'logo' => $secondLogo,
            'description' => 'وصف محدث للمطعم',
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'مطعم كودكسا المحدث')
        ->assertJsonPath('data.phone', '01099999999');

    // Confirm that only 1 record still exists in the database
    $this->assertDatabaseCount('business_setups', 1);

    $updatedSetup = BusinessSetup::first();
    expect($updatedSetup->id)->toBe($initialSetup->id)
        ->and($updatedSetup->name)->toBe('مطعم كودكسا المحدث');

    // Confirm old logo is deleted and new logo exists
    Storage::disk('public')->assertMissing($oldLogoPath);
    Storage::disk('public')->assertExists($updatedSetup->logo);
});

test('admin can update business setup using PUT and existing logo path string', function () {
    $setup = BusinessSetup::create([
        'name' => 'الفرع الرئيسي',
        'phone' => '01234567890',
        'face' => 'https://facebook.com/main',
        'instagram' => 'https://instagram.com/main',
        'whats' => '01234567890',
        'logo' => 'business_setup/existing_logo.png',
        'description' => 'المقر الرئيسي',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/admin/business-setup', [
            'name' => 'الفرع الرئيسي المطور',
            'phone' => '01234567899',
            'face' => 'https://facebook.com/main-updated',
            'instagram' => 'https://instagram.com/main-updated',
            'whats' => '01234567899',
            'logo' => 'business_setup/existing_logo.png',
            'description' => 'المقر الرئيسي بعد التجديد',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'الفرع الرئيسي المطور')
        ->assertJsonPath('data.phone', '01234567899');

    $this->assertDatabaseCount('business_setups', 1);
    expect($setup->fresh()->name)->toBe('الفرع الرئيسي المطور');
});
