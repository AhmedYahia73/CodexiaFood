<?php

use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
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

test('cashier can end currently open shift', function () {
    $shift = StartShift::create([
        'start' => now()->subHours(3),
        'end' => null,
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier1->id,
        'cashier_man_id' => $this->cashierMan->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift')
        ->assertStatus(200)
        ->assertJson([
            'status' => true,
            'message' => 'تم إنهاء الشيفت بنجاح',
        ]);

    expect($shift->fresh()->end)->not->toBeNull();
});

test('cashier receives error when attempting to end shift while none is open', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/cashier/end-shift')
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
        ->postJson('/api/cashier/end-shift')
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
