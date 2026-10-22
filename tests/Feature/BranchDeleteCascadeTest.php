<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleItem;
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
});

test('admin can delete a branch that has associated sales and related items cascade cleanly', function () {
    $branch = Branch::create([
        'name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'],
        'user_name' => 'maadi_branch',
        'password' => 'password123',
    ]);

    $sale = Sale::create([
        'branch_id' => $branch->id,
        'total_cost' => 500.00,
        'paid_amount' => 500.00,
        'remaining_amount' => 0.00,
        'total_quantity' => 2,
    ]);

    $saleItem = SaleItem::create([
        'sale_id' => $sale->id,
        'quantity' => 2,
        'cost' => 250.00,
    ]);

    $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    $this->assertDatabaseHas('sale_items', ['id' => $saleItem->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/admin/branches/{$branch->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Branch deleted successfully.');

    $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    $this->assertDatabaseMissing('sale_items', ['id' => $saleItem->id]);
});
