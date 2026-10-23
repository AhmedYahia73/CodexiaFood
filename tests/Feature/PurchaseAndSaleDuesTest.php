<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\PurchaseDue;
use App\Models\Sale;
use App\Models\SaleDue;
use App\Models\Saler;
use App\Models\Supplier;
use Carbon\Carbon;
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
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
    ]);

    $this->supplier = Supplier::create([
        'name' => 'شركة توريدات المروج',
        'phone' => '01012345678',
        'balance' => 2000.00,
    ]);

    $this->saler = Saler::create([
        'name' => 'عميل جملة الأمل',
        'phone' => '01087654321',
        'balance' => 3000.00,
    ]);
});

/*
|--------------------------------------------------------------------------
| Purchase Dues Tests
|--------------------------------------------------------------------------
*/

test('can list purchase dues with overdue, unpaid, and paid filtering', function () {
    $purchase = Purchase::create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 1500.00,
        'paid_amount' => 500.00,
        'remaining_amount' => 1000.00,
        'total_quantity' => 10,
    ]);

    // Overdue due: date in the past, payment < amount
    $overdueDue = PurchaseDue::create([
        'purchase_id' => $purchase->id,
        'amount' => 400.00,
        'payment' => 100.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // Future unpaid due: date in future, payment < amount
    $futureDue = PurchaseDue::create([
        'purchase_id' => $purchase->id,
        'amount' => 300.00,
        'payment' => 0.00,
        'date' => Carbon::tomorrow()->toDateString(),
    ]);

    // Fully paid due: payment >= amount
    $paidDue = PurchaseDue::create([
        'purchase_id' => $purchase->id,
        'amount' => 300.00,
        'payment' => 300.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // 1. Test /api/admin/purchase-dues/overdue
    $overdueRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchase-dues/overdue');

    $overdueRes->assertOk();
    $overdueIds = collect($overdueRes->json('data'))->pluck('id')->all();
    expect($overdueIds)->toContain($overdueDue->id);
    expect($overdueIds)->not->toContain($futureDue->id);
    expect($overdueIds)->not->toContain($paidDue->id);

    // 2. Test /api/admin/purchase-dues/unpaid
    $unpaidRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchase-dues/unpaid');

    $unpaidRes->assertOk();
    $unpaidIds = collect($unpaidRes->json('data'))->pluck('id')->all();
    expect($unpaidIds)->toContain($overdueDue->id);
    expect($unpaidIds)->toContain($futureDue->id);
    expect($unpaidIds)->not->toContain($paidDue->id);

    // 3. Test /api/admin/purchase-dues/paid
    $paidRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchase-dues/paid');

    $paidRes->assertOk();
    $paidIds = collect($paidRes->json('data'))->pluck('id')->all();
    expect($paidIds)->toContain($paidDue->id);
    expect($paidIds)->not->toContain($overdueDue->id);
    expect($paidIds)->not->toContain($futureDue->id);

    // 4. Test /api/admin/purchase-dues?status=overdue
    $filterRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/purchase-dues?status=overdue');

    $filterRes->assertOk();
    $filterIds = collect($filterRes->json('data'))->pluck('id')->all();
    expect($filterIds)->toContain($overdueDue->id);
    expect($filterIds)->not->toContain($futureDue->id);
});

test('can show single purchase due details', function () {
    $purchase = Purchase::create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 1000.00,
        'paid_amount' => 500.00,
        'remaining_amount' => 500.00,
        'total_quantity' => 5,
    ]);

    $due = PurchaseDue::create([
        'purchase_id' => $purchase->id,
        'amount' => 500.00,
        'payment' => 100.00,
        'date' => Carbon::now()->addDays(5)->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/purchase-dues/{$due->id}");

    $response->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $due->id)
        ->assertJsonPath('data.amount', 500)
        ->assertJsonPath('data.payment', 100)
        ->assertJsonPath('data.remaining', 400)
        ->assertJsonPath('data.supplier.id', $this->supplier->id);
});

test('paying purchase due updates due, purchase amounts, and decrements supplier balance', function () {
    $initialSupplierBalance = 2000.00;
    $this->supplier->update(['balance' => $initialSupplierBalance]);

    $purchase = Purchase::create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $this->supplier->id,
        'total_cost' => 1000.00,
        'paid_amount' => 400.00,
        'remaining_amount' => 600.00,
        'total_quantity' => 5,
    ]);

    $due = PurchaseDue::create([
        'purchase_id' => $purchase->id,
        'amount' => 600.00,
        'payment' => 100.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // Remaining on this due is 500.00
    // Try paying more than 500 => validation error 422
    $failResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/purchase-dues/{$due->id}/pay", [
            'payment' => 500.01,
        ]);

    $failResponse->assertStatus(422);

    // Pay 250.00 partially
    $payResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/purchase-dues/{$due->id}/pay", [
            'payment' => 250.00,
            'notes' => 'سداد دفعة نقدية',
        ]);

    $payResponse->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.payment', 350)
        ->assertJsonPath('data.remaining', 250)
        ->assertJsonPath('meta.paid_now', 250);

    // Check Purchase model
    $purchase->refresh();
    expect((float) $purchase->paid_amount)->toBe(650.00);
    expect((float) $purchase->remaining_amount)->toBe(350.00);

    // Check Supplier balance (reduced by 250)
    $this->supplier->refresh();
    expect((float) $this->supplier->balance)->toBe($initialSupplierBalance - 250.00);

    // Now pay the remaining 250.00 to fully clear the due
    $fullPayResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/purchase-dues/{$due->id}/pay", [
            'payment' => 250.00,
        ]);

    $fullPayResponse->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.remaining', 0);

    // Paying again should fail with 422 because due is fully paid
    $afterPayResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/purchase-dues/{$due->id}/pay", [
            'payment' => 10.00,
        ]);

    $afterPayResponse->assertStatus(422)
        ->assertJsonPath('status', false);
});

/*
|--------------------------------------------------------------------------
| Sale Dues Tests
|--------------------------------------------------------------------------
*/

test('can list sale dues with overdue, unpaid, and paid filtering', function () {
    $sale = Sale::create([
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'total_cost' => 2500.00,
        'paid_amount' => 500.00,
        'remaining_amount' => 2000.00,
        'total_quantity' => 20,
    ]);

    // Overdue due: date in the past, payment < amount
    $overdueDue = SaleDue::create([
        'sale_id' => $sale->id,
        'amount' => 800.00,
        'payment' => 200.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // Future unpaid due: date in future, payment < amount
    $futureDue = SaleDue::create([
        'sale_id' => $sale->id,
        'amount' => 600.00,
        'payment' => 0.00,
        'date' => Carbon::tomorrow()->toDateString(),
    ]);

    // Fully paid due: payment >= amount
    $paidDue = SaleDue::create([
        'sale_id' => $sale->id,
        'amount' => 600.00,
        'payment' => 600.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // 1. Test /api/admin/sale-dues/overdue
    $overdueRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/sale-dues/overdue');

    $overdueRes->assertOk();
    $overdueIds = collect($overdueRes->json('data'))->pluck('id')->all();
    expect($overdueIds)->toContain($overdueDue->id);
    expect($overdueIds)->not->toContain($futureDue->id);
    expect($overdueIds)->not->toContain($paidDue->id);

    // 2. Test /api/admin/sale-dues/unpaid
    $unpaidRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/sale-dues/unpaid');

    $unpaidRes->assertOk();
    $unpaidIds = collect($unpaidRes->json('data'))->pluck('id')->all();
    expect($unpaidIds)->toContain($overdueDue->id);
    expect($unpaidIds)->toContain($futureDue->id);
    expect($unpaidIds)->not->toContain($paidDue->id);

    // 3. Test /api/admin/sale-dues/paid
    $paidRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/sale-dues/paid');

    $paidRes->assertOk();
    $paidIds = collect($paidRes->json('data'))->pluck('id')->all();
    expect($paidIds)->toContain($paidDue->id);
    expect($paidIds)->not->toContain($overdueDue->id);
    expect($paidIds)->not->toContain($futureDue->id);

    // 4. Test /api/admin/sale-dues?status=unpaid
    $filterRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/sale-dues?status=unpaid');

    $filterRes->assertOk();
    $filterIds = collect($filterRes->json('data'))->pluck('id')->all();
    expect($filterIds)->toContain($overdueDue->id);
    expect($filterIds)->toContain($futureDue->id);
    expect($filterIds)->not->toContain($paidDue->id);
});

test('can show single sale due details', function () {
    $sale = Sale::create([
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'total_cost' => 1200.00,
        'paid_amount' => 400.00,
        'remaining_amount' => 800.00,
        'total_quantity' => 10,
    ]);

    $due = SaleDue::create([
        'sale_id' => $sale->id,
        'amount' => 800.00,
        'payment' => 300.00,
        'date' => Carbon::now()->addDays(7)->toDateString(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/admin/sale-dues/{$due->id}");

    $response->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $due->id)
        ->assertJsonPath('data.amount', 800)
        ->assertJsonPath('data.payment', 300)
        ->assertJsonPath('data.remaining', 500)
        ->assertJsonPath('data.saler.id', $this->saler->id);
});

test('paying sale due updates due, sale amounts, and decrements saler balance', function () {
    $initialSalerBalance = 3000.00;
    $this->saler->update(['balance' => $initialSalerBalance]);

    $sale = Sale::create([
        'branch_id' => $this->branch->id,
        'saler_id' => $this->saler->id,
        'total_cost' => 1500.00,
        'paid_amount' => 500.00,
        'remaining_amount' => 1000.00,
        'total_quantity' => 15,
    ]);

    $due = SaleDue::create([
        'sale_id' => $sale->id,
        'amount' => 1000.00,
        'payment' => 200.00,
        'date' => Carbon::yesterday()->toDateString(),
    ]);

    // Remaining on this due is 800.00
    // Try paying more than 800 => validation error 422
    $failResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/sale-dues/{$due->id}/pay", [
            'payment' => 800.01,
        ]);

    $failResponse->assertStatus(422);

    // Pay 400.00 partially
    $payResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/sale-dues/{$due->id}/pay", [
            'payment' => 400.00,
            'notes' => 'سداد جزء من فاتورة البيع الآجل',
        ]);

    $payResponse->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.payment', 600)
        ->assertJsonPath('data.remaining', 400)
        ->assertJsonPath('meta.paid_now', 400);

    // Check Sale model
    $sale->refresh();
    expect((float) $sale->paid_amount)->toBe(900.00);
    expect((float) $sale->remaining_amount)->toBe(600.00);

    // Check Saler balance (decremented by 400)
    $this->saler->refresh();
    expect((float) $this->saler->balance)->toBe($initialSalerBalance - 400.00);

    // Pay the rest (400.00) to clear the due
    $fullPayResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/sale-dues/{$due->id}/pay", [
            'payment' => 400.00,
        ]);

    $fullPayResponse->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.remaining', 0);

    // Paying again should fail with 422
    $afterPayResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/admin/sale-dues/{$due->id}/pay", [
            'payment' => 50.00,
        ]);

    $afterPayResponse->assertStatus(422)
        ->assertJsonPath('status', false);
});
