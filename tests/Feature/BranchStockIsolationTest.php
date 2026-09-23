<?php

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
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

    $this->branchA = Branch::create(['name' => ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch']]);
    $this->branchB = Branch::create(['name' => ['ar' => 'فرع الزمالك', 'en' => 'Zamalek Branch']]);
});

test('purchasing in branch A increments only branch A stock and leaves branch B untouched', function () {
    $material = Material::create(['name' => ['ar' => 'سكر', 'en' => 'Sugar']]);

    // Initial stock: branch A has 10, branch B has 5
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchA->id, 'stock' => 10]);
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchB->id, 'stock' => 5]);

    // Purchase 20 units in branch A
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/purchases', [
            'branch_id' => $this->branchA->id,
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 20,
                    'cost' => 100,
                ],
            ],
        ]);

    $response->assertStatus(201);

    // Branch A should now have 30, branch B must still have 5
    expect($material->stockForBranch($this->branchA->id))->toBe(30.0)
        ->and($material->stockForBranch($this->branchB->id))->toBe(5.0)
        ->and($material->totalStock())->toBe(35.0);
});

test('recording waste in branch A checks only branch A stock and fails if branch A has insufficient stock even if branch B has plenty', function () {
    $material = Material::create(['name' => ['ar' => 'دقيق', 'en' => 'Flour']]);

    // Branch A has only 3, Branch B has 100
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchA->id, 'stock' => 3]);
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchB->id, 'stock' => 100]);

    // Attempt to record 5 units of waste in branch A -> must fail with 422
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/wastes', [
            'branch_id' => $this->branchA->id,
            'material_id' => $material->id,
            'count' => 5,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', false);

    // Stocks must remain unchanged
    expect($material->stockForBranch($this->branchA->id))->toBe(3.0)
        ->and($material->stockForBranch($this->branchB->id))->toBe(100.0);
});

test('manufacturing in branch A checks branch A stock and increments product recipe in branch A only', function () {
    $material = Material::create(['name' => ['ar' => 'بن خام', 'en' => 'Raw Coffee']]);
    $recipe = ProductRecipe::create(['name' => ['ar' => 'خلطة اسبريسو', 'en' => 'Espresso Blend']]);

    // Set stock in branch A
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchA->id, 'stock' => 50]);
    ProductRecipeStock::create(['product_recipe_id' => $recipe->id, 'branch_id' => $this->branchA->id, 'stock' => 0]);

    // Set stock in branch B
    MaterialStock::create(['material_id' => $material->id, 'branch_id' => $this->branchB->id, 'stock' => 20]);
    ProductRecipeStock::create(['product_recipe_id' => $recipe->id, 'branch_id' => $this->branchB->id, 'stock' => 0]);

    // Manufacture 5 units in branch A using 10 units of material
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/admin/manufacturing', [
            'branch_id' => $this->branchA->id,
            'product_recipe_id' => $recipe->id,
            'count' => 5,
            'recipes' => [
                [
                    'material_id' => $material->id,
                    'count' => 10,
                ],
            ],
        ]);

    $response->assertStatus(201);

    // Branch A: material = 40, recipe = 5
    expect($material->stockForBranch($this->branchA->id))->toBe(40.0)
        ->and($recipe->stockForBranch($this->branchA->id))->toBe(5.0);

    // Branch B must be completely untouched: material = 20, recipe = 0
    expect($material->stockForBranch($this->branchB->id))->toBe(20.0)
        ->and($recipe->stockForBranch($this->branchB->id))->toBe(0.0);
});
