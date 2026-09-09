<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'password' => 'password123',
    ]);

    $this->token = JWTAuth::fromUser($this->admin);

    $this->branch = Branch::create([
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
        'status' => true,
    ]);
});

test('unauthenticated request to shift endpoints returns 401', function () {
    $this->getJson('/api/admin/shifts')->assertStatus(401);
    $this->getJson('/api/admin/shifts/select-options')->assertStatus(401);
    $this->postJson('/api/admin/shifts', [])->assertStatus(401);
});

test('admin can fetch branches and shifts in select-options', function () {
    $shift = Shift::create([
        'name' => ['ar' => 'صباحي', 'en' => 'Morning'],
        'start_time' => '08:00',
        'end_time' => '16:00',
        'branch_id' => $this->branch->id,
        'is_tomorrow' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/shifts/select-options')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches',
                'shifts',
            ],
        ]);

    expect($response->json('data.branches.0.id'))->toBe($this->branch->id);
    expect($response->json('data.shifts.0.id'))->toBe($shift->id);
});

test('admin can fetch branches dedicated select-options', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/admin/branches/select-options')
        ->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'branches',
            ],
        ]);

    expect($response->json('data.branches.0.id'))->toBe($this->branch->id);
});

test('is_tomorrow is set to false automatically when end_time is greater than or equal to start_time', function () {
    $payload = [
        'name' => ['ar' => 'شفت نهاري', 'en' => 'Day Shift'],
        'start_time' => '09:00',
        'end_time' => '17:00',
        'branch_id' => $this->branch->id,
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/shifts', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.is_tomorrow', false);

    $this->assertDatabaseHas('shifts', [
        'id' => $response->json('data.id'),
        'is_tomorrow' => false,
    ]);
});

test('is_tomorrow is set to true automatically when end_time is less than start_time', function () {
    $payload = [
        'name' => ['ar' => 'شفت ليلي', 'en' => 'Night Shift'],
        'start_time' => '22:00',
        'end_time' => '06:00',
        'branch_id' => $this->branch->id,
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/shifts', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.is_tomorrow', true);

    $this->assertDatabaseHas('shifts', [
        'id' => $response->json('data.id'),
        'is_tomorrow' => true,
    ]);
});

test('admin can update shift and is_tomorrow recalculates automatically', function () {
    $shift = Shift::create([
        'name' => ['ar' => 'شفت', 'en' => 'Shift'],
        'start_time' => '08:00',
        'end_time' => '16:00',
        'branch_id' => $this->branch->id,
        'is_tomorrow' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/admin/shifts/'.$shift->id, [
            'start_time' => '20:00',
            'end_time' => '04:00',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.is_tomorrow', true);

    expect($shift->fresh()->is_tomorrow)->toBeTrue();
});

test('admin can delete a shift', function () {
    $shift = Shift::create([
        'name' => ['ar' => 'شفت مؤقت', 'en' => 'Temp Shift'],
        'start_time' => '10:00',
        'end_time' => '14:00',
        'branch_id' => $this->branch->id,
        'is_tomorrow' => false,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/admin/shifts/'.$shift->id)
        ->assertStatus(200);

    $this->assertDatabaseMissing('shifts', [
        'id' => $shift->id,
    ]);
});
