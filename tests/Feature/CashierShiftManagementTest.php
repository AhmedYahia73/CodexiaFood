<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Order;
use App\Models\StartShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع النزهة', 'en' => 'Nozha Branch'],
        'status' => true,
    ]);

    $this->cashier1 = Cashier::create([
        'name' => 'POS Desk 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashier2 = Cashier::create([
        'name' => 'POS Desk 2',
        'branch_id' => $this->branch->id,
    ]);

    $this->cashierMan = CashierMan::create([
        'name' => 'Sameh Cashier',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => null,
    ]);

    $this->token = JWTAuth::fromUser($this->cashierMan);
});

test('cashier can start a new shift and update his cashier_id', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/start-shift', [
            'cashier_id' => $this->cashier1->id,
        ])
        ->assertStatus(201)
        ->assertJson([
            'status' => true,
            'message' => 'تم بدء الشيفت بنجاح',
        ])
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.cashier_id', $this->cashier1->id)
        ->assertJsonPath('data.cashier_man_id', $this->cashierMan->id)
        ->assertJsonPath('data.end', null);

    // Verify cashier_id updated on cashier_man
    expect($this->cashierMan->fresh()->cashier_id)->toBe($this->cashier1->id);

    // Verify record in database
    $this->assertDatabaseHas('start_shifts', [
        'id' => $response->json('data.id'),
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'end' => null,
    ]);
});

test('cashier cannot start a new shift if a previous shift is still open', function () {
    // Open a shift first
    StartShift::create([
        'start' => now()->subHours(2),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    // Try starting another shift
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/start-shift', [
            'cashier_id' => $this->cashier2->id,
        ])
        ->assertStatus(400)
        ->assertJson([
            'status' => false,
            'message' => 'يرجى غلق الشيفت السابق اولا',
        ]);
});

test('cashier can end currently open shift and reset cashier_id to null', function () {
    $this->cashierMan->update(['cashier_id' => $this->cashier1->id]);

    $shift = StartShift::create([
        'start' => now()->subHours(3),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift', [
            'total_mony' => 500,
        ])
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'تم إنهاء الشيفت بنجاح',
        ]);

    expect($shift->fresh()->end)->not->toBeNull();
    expect($this->cashierMan->fresh()->cashier_id)->toBeNull();
    expect((float) $shift->fresh()->total_mony)->toBe(500.0);
});

test('cashier receives validation error when attempting to end shift without total_mony', function () {
    $this->cashierMan->update(['cashier_id' => $this->cashier1->id]);

    StartShift::create([
        'start' => now()->subHours(1),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['total_mony']);
});

test('cashier receives error when attempting to end shift while none is open', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift', [
            'total_mony' => 100,
        ])
        ->assertStatus(400)
        ->assertJson([
            'status' => false,
            'message' => 'لا يوجد شيفت مفتوح حالياً',
        ]);
});

test('cashier can start a new shift after ending the previous one', function () {
    // Start first shift
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/start-shift', [
            'cashier_id' => $this->cashier1->id,
        ])
        ->assertStatus(201);

    // End first shift
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift', [
            'total_mony' => 300,
        ])
        ->assertStatus(200);

    // Now start second shift on a different desk
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/start-shift', [
            'cashier_id' => $this->cashier2->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.cashier_id', $this->cashier2->id);

    expect($this->cashierMan->fresh()->cashier_id)->toBe($this->cashier2->id);
});

test('endShift automatically calculates default_total_amount from orders and computes deficit', function () {
    $this->cashierMan->update(['cashier_id' => $this->cashier1->id]);

    $shift = StartShift::create([
        'start' => now()->subHours(2),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    // Create 2 orders during this shift
    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'dine_in',
        'is_pos' => true,
        'total' => 100,
        'final_price' => 120,
    ]);

    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'is_pos' => true,
        'total' => 200,
        'final_price' => 230,
    ]);

    // Total orders final_price = 120 + 230 = 350
    // Cashier hands in total_mony = 300
    // Deficit = 350 - 300 = 50
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift', [
            'total_mony' => 300,
        ])
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.default_total_amount', 350)
        ->assertJsonPath('data.total_mony', 300)
        ->assertJsonPath('data.deficit', 50);

    expect((float) $shift->fresh()->default_total_amount)->toBe(350.0);
    expect((float) $shift->fresh()->total_mony)->toBe(300.0);
    expect((float) $shift->fresh()->deficit)->toBe(50.0);
});

test('admin can view start-shift report and filter by cashier, cashier_man, branch, and date', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'password' => 'password123',
    ]);
    $adminToken = JWTAuth::fromUser($admin);

    $shift = StartShift::create([
        'start' => now()->subDay(),
        'end' => now()->subDay()->addHours(8),
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'default_total_amount' => 500,
        'total_mony' => 450,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/reports/start-shifts?lang=ar&branch_id='.$this->branch->id.'&cashier_id='.$this->cashier1->id);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('summary.total_default_amount', 500)
        ->assertJsonPath('summary.total_collected_mony', 450)
        ->assertJsonPath('summary.total_deficit', 50)
        ->assertJsonPath('shifts.data.0.id', $shift->id)
        ->assertJsonPath('shifts.data.0.default_total_amount', 500)
        ->assertJsonPath('shifts.data.0.total_mony', 450)
        ->assertJsonPath('shifts.data.0.deficit', 50)
        ->assertJsonPath('shifts.data.0.branch_name', 'فرع النزهة')
        ->assertJsonPath('shifts.data.0.cashier_name', 'POS Desk 1')
        ->assertJsonPath('shifts.data.0.cashier_man_name', 'Sameh Cashier');
});

test('admin can fetch start-shifts filter lists supporting cashier_id, cashier_man_id, branch_id with id and name', function () {
    $admin = Admin::create([
        'name' => 'Admin User 2',
        'password' => 'password123',
    ]);
    $adminToken = JWTAuth::fromUser($admin);

    $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/reports/start-shifts/lists?lang=ar&branch_id='.$this->branch->id);

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches' => [
                    '*' => ['id', 'name'],
                ],
                'cashiers' => [
                    '*' => ['id', 'name', 'branch_id'],
                ],
                'cashier_men' => [
                    '*' => ['id', 'name', 'branch_id'],
                ],
            ],
        ]);

    expect($response->json('data.branches.0.name'))->toBe('فرع النزهة')
        ->and($response->json('data.cashiers.0.name'))->toBe('POS Desk 1')
        ->and($response->json('data.cashier_men.0.name'))->toBe('Sameh Cashier');
});

test('admin can paginate start-shift report with page parameter', function () {
    $admin = Admin::create([
        'name' => 'Admin User 3',
        'password' => 'password123',
    ]);
    $adminToken = JWTAuth::fromUser($admin);

    StartShift::create([
        'start' => now()->subDays(3),
        'end' => now()->subDays(3)->addHours(8),
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'default_total_amount' => 100,
        'total_mony' => 100,
    ]);

    StartShift::create([
        'start' => now()->subDays(2),
        'end' => now()->subDays(2)->addHours(8),
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'default_total_amount' => 200,
        'total_mony' => 200,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/admin/reports/start-shifts?page=2&per_page=1');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 1);

    expect($response->json('data'))->toHaveCount(1);
});
