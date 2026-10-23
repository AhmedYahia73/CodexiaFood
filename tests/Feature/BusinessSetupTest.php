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

test('admin can set has_gift and price_gift keys in business setup', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم الهدايا',
            'phone' => '01011112222',
            'face' => 'https://facebook.com/gift',
            'instagram' => 'https://instagram.com/gift',
            'whats' => '01011112222',
            'logo' => 'business_setup/gift_logo.png',
            'description' => 'عروض هدايا مستمرة',
            'has_gift' => true,
            'price_gift' => 2,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.has_gift', true)
        ->assertJsonPath('data.price_gift', 2);

    $setup = BusinessSetup::first();
    expect($setup->has_gift)->toBeTrue()
        ->and($setup->price_gift)->toBe(2);
});

test('admin can save business setup with menu images and see them as links', function () {
    Storage::fake('public');

    $logo = UploadedFile::fake()->image('logo.png');
    $menu1 = UploadedFile::fake()->image('menu1.png');
    $menu2 = UploadedFile::fake()->image('menu2.png');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم المنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/menu',
            'instagram' => 'https://instagram.com/menu',
            'whats' => '01012345678',
            'logo' => $logo,
            'description' => 'وصف المطعم',
            'menu' => [$menu1, $menu2],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true);

    $setup = BusinessSetup::first();
    expect($setup->menu)->toBeArray()
        ->and($setup->menu)->toHaveCount(2);

    foreach ($setup->menu as $path) {
        Storage::disk('public')->assertExists($path);
    }

    $links = $response->json('data.menu');
    expect($links)->toHaveCount(2)
        ->and($links[0])->toContain(url('storage/'))
        ->and($links[1])->toContain(url('storage/'));
});

test('admin can update business setup menu and clean up removed images', function () {
    Storage::fake('public');

    $logo = UploadedFile::fake()->image('logo.png');
    $menu1 = UploadedFile::fake()->image('menu1.png');
    $menu2 = UploadedFile::fake()->image('menu2.png');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم التحديث',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/update',
            'instagram' => 'https://instagram.com/update',
            'whats' => '01012345678',
            'logo' => $logo,
            'description' => 'وصف المطعم',
            'menu' => [$menu1, $menu2],
        ])->assertStatus(200);

    $setup = BusinessSetup::first();
    $oldMenu = $setup->menu;
    $removedFile = $oldMenu[0];
    $keptFile = $oldMenu[1];

    $newMenu3 = UploadedFile::fake()->image('menu3.png');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم التحديث 2',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/update',
            'instagram' => 'https://instagram.com/update',
            'whats' => '01012345678',
            'logo' => $setup->logo,
            'description' => 'وصف المطعم',
            'menu' => [$keptFile, $newMenu3],
        ]);

    $response->assertStatus(200);

    Storage::disk('public')->assertMissing($removedFile);
    Storage::disk('public')->assertExists($keptFile);

    $updatedSetup = $setup->fresh();
    expect($updatedSetup->menu)->toHaveCount(2)
        ->and($updatedSetup->menu)->toContain($keptFile);
});

test('admin can set menu to null or empty array', function () {
    Storage::fake('public');

    $logo = UploadedFile::fake()->image('logo.png');
    $menuFile = UploadedFile::fake()->image('menu.png');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم تفريغ المنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/clear',
            'instagram' => 'https://instagram.com/clear',
            'whats' => '01012345678',
            'logo' => $logo,
            'description' => 'وصف المطعم',
            'menu' => [$menuFile],
        ])->assertStatus(200);

    $setup = BusinessSetup::first();
    $storedPath = $setup->menu[0];
    Storage::disk('public')->assertExists($storedPath);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم تفريغ المنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/clear',
            'instagram' => 'https://instagram.com/clear',
            'whats' => '01012345678',
            'logo' => $setup->logo,
            'description' => 'وصف المطعم',
            'menu' => null,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.menu', null);

    Storage::disk('public')->assertMissing($storedPath);
    expect($setup->fresh()->menu)->toBeNull();
});

test('admin can delete specific image from menu using deleted_menu key', function () {
    Storage::fake('public');

    $file1 = UploadedFile::fake()->image('page1.png');
    $file2 = UploadedFile::fake()->image('page2.png');

    $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم فحص حذف المنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/test',
            'instagram' => 'https://instagram.com/test',
            'whats' => '01012345678',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'description' => 'وصف',
            'menu' => [$file1, $file2],
        ])->assertStatus(200);

    $setup = BusinessSetup::first();
    expect($setup->menu)->toHaveCount(2);
    $imgToDelete = $setup->menu[0];
    $imgToKeep = $setup->menu[1];

    Storage::disk('public')->assertExists($imgToDelete);
    Storage::disk('public')->assertExists($imgToKeep);

    // Call update on business-setup/1 using deleted_menu with full URL or relative path
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup/'.$setup->id, [
            'name' => 'مطعم فحص حذف المنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/test',
            'instagram' => 'https://instagram.com/test',
            'whats' => '01012345678',
            'logo' => $setup->logo,
            'description' => 'وصف',
            'deleted_menu' => [url('storage/'.$imgToDelete)],
        ]);

    $response->assertStatus(200);

    Storage::disk('public')->assertMissing($imgToDelete);
    Storage::disk('public')->assertExists($imgToKeep);

    $freshMenu = $setup->fresh()->menu;
    expect($freshMenu)->toHaveCount(1)
        ->and($freshMenu)->toContain($imgToKeep)
        ->and($freshMenu)->not->toContain($imgToDelete);
});

test('admin can append a new image to existing menu images without replacing them', function () {
    Storage::fake('public');

    $file1 = UploadedFile::fake()->image('page1.png');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup', [
            'name' => 'مطعم الإضافة للمنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/test',
            'instagram' => 'https://instagram.com/test',
            'whats' => '01012345678',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'description' => 'وصف',
            'menu' => [$file1],
        ])->assertStatus(200);

    $setup = BusinessSetup::first();
    $firstImagePath = $setup->menu[0];

    // Upload a second image to append to the existing menu
    $file2 = UploadedFile::fake()->image('page2.png');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/business-setup/'.$setup->id, [
            'name' => 'مطعم الإضافة للمنيو',
            'phone' => '01012345678',
            'face' => 'https://facebook.com/test',
            'instagram' => 'https://instagram.com/test',
            'whats' => '01012345678',
            'logo' => $setup->logo,
            'description' => 'وصف',
            'menu' => [$file2],
        ]);

    $response->assertStatus(200);

    $freshMenu = $setup->fresh()->menu;
    expect($freshMenu)->toHaveCount(2)
        ->and($freshMenu)->toContain($firstImagePath);

    Storage::disk('public')->assertExists($firstImagePath);
    Storage::disk('public')->assertExists($freshMenu[1]);
});
