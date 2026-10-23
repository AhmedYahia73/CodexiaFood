<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\StartShift;
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

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'user_name' => 'maadi_branch',
        'password' => 'password123',
    ]);
});

test('cashier index returns cashier_man name and status active when shift is open', function () {
    $cashier = Cashier::create([
        'name' => 'POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $cashierMan = CashierMan::create([
        'name' => 'أحمد علي',
        'user_name' => 'ahmed_ali',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $cashier->id,
    ]);

    // Active open shift: end is null
    StartShift::create([
        'start' => now(),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $cashier->id,
        'cashier_man_id' => $cashierMan->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/cashiers?page=1&per_page=10');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.id', $cashier->id)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.cashier_man', 'أحمد علي')
        ->assertJsonPath('data.0.cashier_man_name', 'أحمد علي');
});

test('cashier index returns in_active when no open shift exists', function () {
    $cashier = Cashier::create([
        'name' => 'POS 2',
        'branch_id' => $this->branch->id,
    ]);

    $cashierMan = CashierMan::create([
        'name' => 'محمود حسن',
        'user_name' => 'mahmoud_hassan',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
    ]);

    // Closed shift: end is set
    StartShift::create([
        'start' => now()->subHours(8),
        'end' => now()->subHour(),
        'branch_id' => $this->branch->id,
        'cashier_id' => $cashier->id,
        'cashier_man_id' => $cashierMan->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/cashiers?page=1&per_page=10');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.id', $cashier->id)
        ->assertJsonPath('data.0.status', 'in_active');
});

test('cashier index resolves cashier_man directly assigned to cashier when shift is not open', function () {
    $cashierMan = CashierMan::create([
        'name' => 'سارة محمد',
        'user_name' => 'sara_mohamed',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
    ]);

    $cashier = Cashier::create([
        'name' => 'POS 3',
        'branch_id' => $this->branch->id,
        'cashier_man_id' => $cashierMan->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/cashiers');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.status', 'in_active')
        ->assertJsonPath('data.0.cashier_man', 'سارة محمد')
        ->assertJsonPath('data.0.cashier_man_name', 'سارة محمد');
});
