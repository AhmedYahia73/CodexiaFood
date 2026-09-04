<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\CashierMan;
use App\Models\Hall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    $this->token = JWTAuth::fromUser($this->admin);
});

test('unauthenticated request to admin endpoints returns 401', function () {
    $response = $this->getJson('/api/admin/admins');

    $response->assertStatus(401);
});

test('authenticated admin can access admin routes', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/admins');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('admin login returns jwt access token', function () {
    $response = $this->postJson('/api/auth/login', [
        'name' => 'Super Admin',
        'password' => 'password123',
        'guard' => 'admin',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
});

test('admin can perform CRUD on Admin model', function () {
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/admins', [
            'name' => 'New Admin',
            'password' => 'secret123',
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'New Admin');

    $adminId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/admins/{$adminId}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'New Admin');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/admin/admins/{$adminId}", [
            'name' => 'Updated Admin Name',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Admin Name');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/admins/{$adminId}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('admins', ['id' => $adminId]);
});

test('admin can perform CRUD on Branch model', function () {
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/branches', [
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'watts' => '01000000000',
            'facebook' => 'fb.com/mainbranch',
            'password' => 'branchpass',
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Main Branch');

    $branchId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/branches/{$branchId}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Main Branch');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/branches/{$branchId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on CashierMan model with hashed password', function () {
    $branch = Branch::create(['name' => 'Branch A']);

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/cashier-men', [
            'name' => 'John CashierMan',
            'password' => 'cashierpass123',
            'branch_id' => $branch->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'John CashierMan')
        ->assertJsonStructure(['select_options' => ['branches', 'cashiers']]);

    $cashierManId = $storeResponse->json('data.id');

    $cashierMan = CashierMan::find($cashierManId);
    expect(Hash::check('cashierpass123', $cashierMan->password))->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/cashier-men/{$cashierManId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on Cashier model', function () {
    $branch = Branch::create(['name' => 'Branch B']);

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/cashiers', [
            'name' => 'POS 1',
            'branch_id' => $branch->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'POS 1');

    $cashierId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/cashiers/{$cashierId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on Delivery model with storage url images array', function () {
    $file1 = UploadedFile::fake()->image('id1.jpg');
    $file2 = UploadedFile::fake()->image('id2.jpg');

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/deliveries', [
            'name' => 'Fast Delivery Guy',
            'phone' => '01111111111',
            'id_images' => [$file1, $file2],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Fast Delivery Guy');

    $images = $storeResponse->json('data.id_images');
    expect($images)->toBeArray()->toHaveCount(2);
    expect($images[0])->toContain('storage/deliveries/');

    $deliveryId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/deliveries/{$deliveryId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on Category model with storage url image', function () {
    $imageFile = UploadedFile::fake()->image('category.png');

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/categories', [
            'name' => ['en' => 'Drinks', 'ar' => 'مشروبات'],
            'image' => $imageFile,
            'description' => ['en' => 'Hot and cold drinks', 'ar' => 'مشروبات ساخنة وباردة'],
            'type' => 'product',
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Drinks')
        ->assertJsonPath('data.name.ar', 'مشروبات');

    $imageUrl = $storeResponse->json('data.image');
    expect($imageUrl)->toContain('storage/categories/');

    $categoryId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/categories/{$categoryId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on Discount model', function () {
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/discounts', [
            'name' => ['en' => 'Summer Sale 2026', 'ar' => 'تخفيضات صيف 2026'],
            'type' => 'percentage',
            'amount' => 15.00,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Summer Sale 2026')
        ->assertJsonPath('data.name.ar', 'تخفيضات صيف 2026')
        ->assertJsonPath('data.amount', 15);

    $discountId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/discounts/{$discountId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on ExpenseList model', function () {
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/expense-lists', [
            'name' => ['en' => 'Kitchen Supplies Expense', 'ar' => 'مصروفات مستلزمات المطبخ'],
            'description' => ['en' => 'General kitchen purchases', 'ar' => 'مشتريات المطبخ العامة'],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Kitchen Supplies Expense')
        ->assertJsonPath('data.name.ar', 'مصروفات مستلزمات المطبخ');

    $expenseListId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/expense-lists/{$expenseListId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on FinancialAccount model with storage icon URL & branch select options', function () {
    $branch = Branch::create(['name' => 'Branch Finance']);
    $iconFile = UploadedFile::fake()->image('account_icon.png');

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/financial-accounts', [
            'name' => ['en' => 'Cash Safe A', 'ar' => 'خزنة نقدية أ'],
            'icon' => $iconFile,
            'balance' => 5000.50,
            'status' => true,
            'branch_id' => $branch->id,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name.en', 'Cash Safe A')
        ->assertJsonPath('data.name.ar', 'خزنة نقدية أ')
        ->assertJsonPath('data.balance', 5000.5)
        ->assertJsonStructure(['select_options' => ['branches']]);

    $iconUrl = $storeResponse->json('data.icon');
    expect($iconUrl)->toContain('storage/financial_accounts/');

    $branchesList = $storeResponse->json('select_options.branches');
    expect($branchesList)->toBeArray();
    expect($branchesList[0])->toHaveKeys(['id', 'name']);

    $accountId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/financial-accounts/{$accountId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on Hall model with branch select options', function () {
    $branch = Branch::create(['name' => 'Branch Hall']);

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/halls', [
            'name' => ['en' => 'VIP VIP Hall', 'ar' => 'قاعة كبار الزوار'],
            'branch_id' => $branch->id,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name.en', 'VIP VIP Hall')
        ->assertJsonPath('data.name.ar', 'قاعة كبار الزوار')
        ->assertJsonStructure(['select_options' => ['branches']]);

    $hallId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/halls/{$hallId}")
        ->assertStatus(200);
});

test('admin can perform CRUD on HallTable model with branches & halls select options', function () {
    $branch = Branch::create(['name' => 'Branch Hall Table']);
    $hall = Hall::create(['name' => 'Main Hall', 'branch_id' => $branch->id]);

    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/hall-tables', [
            'name' => 'Table #5',
            'branch_id' => $branch->id,
            'hall_id' => $hall->id,
            'status' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Table #5')
        ->assertJsonStructure(['select_options' => ['branches', 'halls']]);

    $selectBranches = $storeResponse->json('select_options.branches');
    $selectHalls = $storeResponse->json('select_options.halls');

    expect($selectBranches[0])->toHaveKeys(['id', 'name']);
    expect($selectHalls[0])->toHaveKeys(['id', 'name']);

    $tableId = $storeResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/hall-tables/{$tableId}")
        ->assertStatus(200);
});

test('admin can fetch dedicated select-options for all controllers returning strictly id and name', function () {
    $branch = Branch::create(['name' => 'Select Branch']);
    $hall = Hall::create(['name' => 'Select Hall', 'branch_id' => $branch->id]);

    $endpoints = [
        '/api/admin/admins/select-options' => ['roles'],
        '/api/admin/cashier-men/select-options' => ['branches', 'cashiers'],
        '/api/admin/cashiers/select-options' => ['branches', 'cashier_men'],
        '/api/admin/deliveries/select-options' => ['branches'],
        '/api/admin/categories/select-options' => ['categories'],
        '/api/admin/financial-accounts/select-options' => ['branches'],
        '/api/admin/halls/select-options' => ['branches'],
        '/api/admin/hall-tables/select-options' => ['branches', 'halls'],
    ];

    foreach ($endpoints as $endpoint => $keys) {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson($endpoint);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        foreach ($keys as $key) {
            $data = $response->json("data.{$key}");
            expect($data)->toBeArray();
        }
    }
});
