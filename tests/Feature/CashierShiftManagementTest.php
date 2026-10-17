<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Expense;
use App\Models\ExpenseList;
use App\Models\FinancialAccount;
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
        'status' => 'delivered',
        'total' => 100,
        'final_price' => 120,
    ]);

    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'is_pos' => true,
        'status' => 'pickup',
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

test('cashier can start shift with start_amount and financial_account_id', function () {
    $account = FinancialAccount::create([
        'name' => ['ar' => 'خزينة الكاشير', 'en' => 'Cashier Safe'],
        'status' => true,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/start-shift', [
            'cashier_id' => $this->cashier1->id,
            'start_amount' => 150.50,
            'financial_account_id' => $account->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.start_amount', 150.50)
        ->assertJsonPath('data.financial_account_id', $account->id);

    $this->assertDatabaseHas('start_shifts', [
        'id' => $response->json('data.id'),
        'start_amount' => 150.50,
        'financial_account_id' => $account->id,
    ]);
});

test('cashier can fetch active financial accounts with localized name and icon', function () {
    FinancialAccount::create([
        'name' => ['ar' => 'الخزينة النقدية', 'en' => 'Cash Safe'],
        'icon' => 'icons/cash.png',
        'status' => true,
        'balance' => 1000,
        'branch_id' => $this->branch->id,
    ]);

    FinancialAccount::create([
        'name' => ['ar' => 'حساب غير نشط', 'en' => 'Inactive Account'],
        'status' => false,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/financial-accounts?lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('الخزينة النقدية');

    $enResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/financial-accounts?lang=en')
        ->assertStatus(200);

    expect($enResponse->json('data.0.name'))->toBe('Cash Safe');
});

test('cashier can fetch expense lists with localized name and description', function () {
    ExpenseList::create([
        'name' => ['ar' => 'مصروفات نظافة', 'en' => 'Cleaning Expenses'],
        'description' => ['ar' => 'شراء مساحيق وأدوات', 'en' => 'Supplies'],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/expense-lists?lang=ar')
        ->assertStatus(200)
        ->assertJsonPath('status', true);

    expect($response->json('data.0.name'))->toBe('مصروفات نظافة');
});

test('cashier can record an expense and it automatically binds cashier_id, cashier_man_id, shift_id', function () {
    $account = FinancialAccount::create([
        'name' => ['ar' => 'درج الكاشير', 'en' => 'Cash Drawer'],
        'status' => true,
        'branch_id' => $this->branch->id,
    ]);

    $expenseList = ExpenseList::create([
        'name' => ['ar' => 'صيانة سريعة', 'en' => 'Quick Maintenance'],
    ]);

    $this->cashierMan->update(['cashier_id' => $this->cashier1->id]);

    $shift = StartShift::create([
        'start' => now()->subHours(1),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'start_amount' => 200,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/expenses', [
            'expense_list_id' => $expenseList->id,
            'financial_id' => $account->id,
            'amount' => 45.50,
            'description' => 'شراء لمبة جديدة',
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.amount', 45.50)
        ->assertJsonPath('data.shift_id', $shift->id)
        ->assertJsonPath('data.cashier_man_id', $this->cashierMan->id)
        ->assertJsonPath('data.cashier_id', $this->cashier1->id);

    $this->assertDatabaseHas('expenses', [
        'id' => $response->json('data.id'),
        'shift_id' => $shift->id,
        'financial_id' => $account->id,
        'expense_list_id' => $expenseList->id,
        'amount' => 45.50,
    ]);

    // Test listing expenses
    $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/cashier/expenses')
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('total_amount', 45.50);

    expect($listResponse->json('data'))->toHaveCount(1);
});

test('endShift computes deficit using formula: orders_total - total_mony - expenses_total + start_amount', function () {
    $account = FinancialAccount::create([
        'name' => ['ar' => 'خزينة الصندوق', 'en' => 'Register Safe'],
        'status' => true,
        'branch_id' => $this->branch->id,
    ]);

    $expenseList = ExpenseList::create([
        'name' => ['ar' => 'ضيافة عملاء', 'en' => 'Hospitality'],
    ]);

    $this->cashierMan->update(['cashier_id' => $this->cashier1->id]);

    // Start shift with starting amount 100
    $shift = StartShift::create([
        'start' => now()->subHours(4),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'start_amount' => 100.00,
        'financial_account_id' => $account->id,
    ]);

    // Orders delivered / pickup during shift: total 500
    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'dinein',
        'is_pos' => true,
        'status' => 'delivered',
        'total' => 300,
        'final_price' => 300,
    ]);

    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'takeaway',
        'is_pos' => true,
        'status' => 'pickup',
        'total' => 200,
        'final_price' => 200,
    ]);

    // Order still preparing (not delivered/pickup) -> should NOT be included in cash
    Order::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'module' => 'dinein',
        'is_pos' => true,
        'status' => 'preparing',
        'total' => 999,
        'final_price' => 999,
    ]);

    // Expenses during shift: total 70
    Expense::create([
        'shift_id' => $shift->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
        'financial_id' => $account->id,
        'expense_list_id' => $expenseList->id,
        'amount' => 70,
    ]);

    // Expected drawer amount = start_amount (100) + orders_total (500) - expenses_total (70) = 530
    // Cashier enters total_mony = 500
    // Deficit = Expected (530) - Actual (500) = 30
    // (Formula: orders_total (500) - total_mony (500) - expenses_total (70) + start_amount (100) = 30)
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift', [
            'total_mony' => 500,
        ])
        ->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.start_amount', 100)
        ->assertJsonPath('data.orders_total', 500)
        ->assertJsonPath('data.expenses_total', 70)
        ->assertJsonPath('data.default_total_amount', 530)
        ->assertJsonPath('data.total_mony', 500)
        ->assertJsonPath('data.deficit', 30);

    $freshShift = $shift->fresh();
    expect((float) $freshShift->start_amount)->toBe(100.0)
        ->and((float) $freshShift->orders_total)->toBe(500.0)
        ->and((float) $freshShift->expenses_total)->toBe(70.0)
        ->and((float) $freshShift->default_total_amount)->toBe(530.0)
        ->and((float) $freshShift->total_mony)->toBe(500.0)
        ->and((float) $freshShift->deficit)->toBe(30.0);
});
