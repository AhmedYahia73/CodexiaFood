<?php

use App\Models\Branch;
use App\Models\CashierMan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
        'status' => true,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'cashier-user',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
    ]);
});

test('cashier can login successfully with name, password, and guard cashier_man', function () {
    $response = $this->withHeader('lang', 'ar')->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'Successfully authenticated',
        ])
        ->assertJsonStructure([
            'status',
            'data' => [
                'access_token',
                'token_type',
                'expires_at',
                'expires_in',
                'guard',
                'user' => [
                    'id',
                    'name',
                    'branch_id',
                    'branch_name',
                    'role',
                ],
            ],
        ]);

    expect($response->json('data.token_type'))->toBe('Bearer')
        ->and($response->json('data.guard'))->toBe('cashier_man')
        ->and($response->json('data.user.id'))->toBe($this->cashierMan->id)
        ->and($response->json('data.user.name'))->toBe('cashier-user')
        ->and($response->json('data.user.branch_id'))->toBe($this->branch->id)
        ->and($response->json('data.user.branch_name'))->toBe('الفرع الرئيسي')
        ->and($response->json('data.expires_at'))->not()->toBeEmpty();
});

test('cashier can authenticate with lang=en to receive english branch name', function () {
    $response = $this->withHeader('lang', 'en')->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.user.branch_name'))->toBe('Main Branch');
});

test('login fails with 401 when credentials are wrong', function () {
    $response = $this->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'wrong-password',
        'guard' => 'cashier_man',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'status' => false,
            'message' => 'Unauthorized: Invalid login credentials.',
        ]);
});

test('cashier can retrieve authenticated user profile via get auth me', function () {
    $login = $this->withHeader('lang', 'ar')->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $token = $login->json('data.access_token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('lang', 'ar')
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
            'data' => [
                'id' => $this->cashierMan->id,
                'name' => 'cashier-user',
                'branch_id' => $this->branch->id,
                'branch_name' => 'الفرع الرئيسي',
            ],
        ]);
});

test('cashier can refresh token via post auth refresh', function () {
    $login = $this->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $token = $login->json('data.access_token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(200)
        ->assertJson([
            'status' => true,
        ])
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'token_type',
                'expires_at',
            ],
        ]);

    expect($response->json('data.access_token'))->not()->toBeEmpty();
});

test('cashier can logout via post auth logout', function () {
    $login = $this->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $token = $login->json('data.access_token');

    $logout = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout');

    $logout->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'Successfully logged out.',
        ]);
});

test('cashier endpoints accept issued token', function () {
    $login = $this->postJson('/api/auth/login', [
        'name' => 'cashier-user',
        'password' => 'secret123',
        'guard' => 'cashier_man',
    ]);

    $token = $login->json('data.access_token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/cashier/products');

    $response->assertStatus(200)
        ->assertJson(['status' => true]);
});

test('unauthenticated request returns 401 json and does not redirect to web login route', function () {
    $response = $this->getJson('/api/cashier/products');

    $response->assertStatus(401);
    expect($response->headers->get('content-type'))->toContain('application/json');
});
