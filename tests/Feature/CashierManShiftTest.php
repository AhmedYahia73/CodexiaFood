<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Shift;
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
        'status' => true,
    ]);

    $this->cashier = Cashier::create([
        'name' => 'Cashier POS 1',
        'branch_id' => $this->branch->id,
    ]);

    $this->shift = Shift::create([
        'name' => ['ar' => 'وردية الصباح', 'en' => 'Morning Shift'],
        'start_time' => '08:00',
        'end_time' => '16:00',
        'branch_id' => $this->branch->id,
        'is_tomorrow' => false,
    ]);
});

test('admin can fetch cashier-men select-options containing shifts', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/cashier-men/select-options')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches',
                'cashiers',
                'shifts',
            ],
        ]);

    expect($response->json('data.shifts.0.id'))->toBe($this->shift->id);
});

test('admin can create cashier-man with shift_id', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/cashier-men', [
            'name' => 'Ahmed Cashier',
            'password' => 'password123',
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.shift_id', $this->shift->id)
        ->assertJsonPath('data.shift.id', $this->shift->id);

    $this->assertDatabaseHas('cashier_men', [
        'name' => 'Ahmed Cashier',
        'shift_id' => $this->shift->id,
    ]);
});

test('admin can update cashier-man shift_id', function () {
    $cashierMan = CashierMan::create([
        'name' => 'Mohamed Cashier',
        'password' => 'secret123',
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->cashier->id,
        'shift_id' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/admin/cashier-men/'.$cashierMan->id, [
            'shift_id' => $this->shift->id,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.shift_id', $this->shift->id)
        ->assertJsonPath('data.shift.id', $this->shift->id);

    expect($cashierMan->fresh()->shift_id)->toBe($this->shift->id);
});
