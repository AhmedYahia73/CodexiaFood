<?php

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Super admin without role_id has full access
    $this->superAdmin = Admin::create([
        'name' => 'Super Administrator',
        'password' => 'supersecret123',
        'role_id' => null,
    ]);

    $this->superAdminToken = JWTAuth::fromUser($this->superAdmin);
});

test('admin with role_id = null has full access to all admin routes', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson('/api/admin/roles');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);

    expect($this->superAdmin->hasPermission('purchase', 'view'))->toBeTrue();
    expect($this->superAdmin->hasPermission('purchase', 'add'))->toBeTrue();
    expect($this->superAdmin->hasPermission('anything', 'delete'))->toBeTrue();
    expect($this->superAdmin->can('purchase.view'))->toBeTrue();
    expect($this->superAdmin->can('view', 'purchase'))->toBeTrue();
});

test('api for permissions list returns dictionary of all permissions and actions', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson('/api/admin/roles/permissions');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                'purchase',
                'product',
                'order',
                'role',
                'admin',
            ],
        ]);

    $purchaseActions = $response->json('data.purchase');
    expect($purchaseActions)->toBeArray()
        ->toContain('all', 'view', 'add', 'update', 'delete');

    // Also verify /api/admin/roles/lists alias endpoint
    $listsResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson('/api/admin/roles/lists');

    $listsResponse->assertStatus(200)
        ->assertJsonPath('status', true);
});

test('can fetch select-options for roles', function () {
    Role::create(['name' => 'Accountant']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson('/api/admin/roles/select-options');

    $response->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'data' => [
                'roles',
                'permissions',
            ],
        ]);
});

test('admin can perform complete CRUD on Role model with permissions', function () {
    // 1. Store role with associative permissions map
    $storeResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->postJson('/api/admin/roles', [
            'name' => 'Purchase Specialist',
            'permissions' => [
                'purchase' => ['view', 'add'],
                'category' => ['view'],
            ],
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'Purchase Specialist')
        ->assertJsonPath('data.grouped_permissions.purchase', ['view', 'add'])
        ->assertJsonPath('data.grouped_permissions.category', ['view']);

    $roleId = $storeResponse->json('data.id');
    expect($roleId)->not->toBeNull();

    // Verify stored permissions in database
    $this->assertDatabaseHas('permissions', [
        'role_id' => $roleId,
        'name' => 'purchase',
        'action' => 'view',
    ]);
    $this->assertDatabaseHas('permissions', [
        'role_id' => $roleId,
        'name' => 'purchase',
        'action' => 'add',
    ]);
    $this->assertDatabaseHas('permissions', [
        'role_id' => $roleId,
        'name' => 'category',
        'action' => 'view',
    ]);

    // 2. Show role by id
    $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson("/api/admin/roles/{$roleId}");

    $showResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $roleId)
        ->assertJsonPath('data.name', 'Purchase Specialist')
        ->assertJsonPath('data.grouped_permissions.purchase', ['view', 'add']);

    // 3. Index returns only role data (without permissions clutter)
    $indexResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->getJson('/api/admin/roles');

    $indexResponse->assertStatus(200)
        ->assertJsonPath('data.0.id', $roleId)
        ->assertJsonPath('data.0.name', 'Purchase Specialist')
        ->assertJsonMissingPath('data.0.grouped_permissions');

    // 4. Update role name and sync new permissions
    $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->putJson("/api/admin/roles/{$roleId}", [
            'name' => 'Senior Purchase Specialist',
            'permissions' => [
                'purchase' => ['all'],
            ],
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'Senior Purchase Specialist')
        ->assertJsonPath('data.grouped_permissions.purchase', ['all']);

    // Verify old permissions replaced with new one
    $this->assertDatabaseMissing('permissions', [
        'role_id' => $roleId,
        'name' => 'category',
    ]);
    $this->assertDatabaseHas('permissions', [
        'role_id' => $roleId,
        'name' => 'purchase',
        'action' => 'all',
    ]);

    // 5. Delete role
    $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->deleteJson("/api/admin/roles/{$roleId}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('status', true);

    $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    $this->assertDatabaseMissing('permissions', ['role_id' => $roleId]);
});

test('store role also accepts list of permission objects and string formats', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->superAdminToken)
        ->postJson('/api/admin/roles', [
            'name' => 'Inventory Staff',
            'permissions' => [
                ['name' => 'inventory', 'action' => 'view'],
                ['name' => 'material', 'action' => 'add'],
                'product.view',
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Inventory Staff')
        ->assertJsonPath('data.grouped_permissions.inventory', ['view'])
        ->assertJsonPath('data.grouped_permissions.material', ['add'])
        ->assertJsonPath('data.grouped_permissions.product', ['view']);
});

test('admin with assigned role is restricted according to admins.role.permissions', function () {
    // Create role with only view access to purchase
    $role = Role::create(['name' => 'Viewer Only']);
    Permission::create([
        'role_id' => $role->id,
        'name' => 'purchase',
        'action' => 'view',
    ]);

    $restrictedAdmin = Admin::create([
        'name' => 'Restricted Admin',
        'password' => 'secret123',
        'role_id' => $role->id,
    ]);

    $restrictedToken = JWTAuth::fromUser($restrictedAdmin);

    // 1. Can access view endpoint (index) for purchase
    $viewResponse = $this->withHeader('Authorization', 'Bearer '.$restrictedToken)
        ->getJson('/api/admin/purchases');

    $viewResponse->assertStatus(200);

    // 2. Cannot access add endpoint (store) for purchase -> 403 Forbidden
    $addResponse = $this->withHeader('Authorization', 'Bearer '.$restrictedToken)
        ->postJson('/api/admin/purchases', [
            'items' => [],
        ]);

    $addResponse->assertStatus(403)
        ->assertJsonPath('status', false);

    // 3. Cannot access another module without permission (e.g. orders) -> 403 Forbidden
    $orderResponse = $this->withHeader('Authorization', 'Bearer '.$restrictedToken)
        ->getJson('/api/admin/orders');

    $orderResponse->assertStatus(403)
        ->assertJsonPath('status', false);
});

test('admin with action = all has full access to that module', function () {
    $role = Role::create(['name' => 'Category Manager']);
    Permission::create([
        'role_id' => $role->id,
        'name' => 'category',
        'action' => 'all',
    ]);

    $manager = Admin::create([
        'name' => 'Category Boss',
        'password' => 'secret123',
        'role_id' => $role->id,
    ]);

    $managerToken = JWTAuth::fromUser($manager);

    expect($manager->hasPermission('category', 'view'))->toBeTrue();
    expect($manager->hasPermission('category', 'add'))->toBeTrue();
    expect($manager->hasPermission('category', 'update'))->toBeTrue();
    expect($manager->hasPermission('category', 'delete'))->toBeTrue();
    expect($manager->hasPermission('purchase', 'view'))->toBeFalse();

    // Can access index
    $this->withHeader('Authorization', 'Bearer '.$managerToken)
        ->getJson('/api/admin/categories')
        ->assertStatus(200);

    // Cannot access purchases
    $this->withHeader('Authorization', 'Bearer '.$managerToken)
        ->getJson('/api/admin/purchases')
        ->assertStatus(403);
});

test('role and permission relations and model helpers work properly', function () {
    $role = Role::create(['name' => 'Custom Role']);
    $role->syncPermissions([
        'purchase' => ['view', 'add'],
        'sale' => ['all'],
    ]);

    $admin = Admin::create([
        'name' => 'Test Admin',
        'password' => 'secret123',
        'role_id' => $role->id,
    ]);

    // Test admins.role.permissions
    expect($admin->role)->not->toBeNull();
    expect($admin->role->id)->toBe($role->id);
    expect($admin->role->permissions)->toHaveCount(3);
    expect($admin->permissions)->toHaveCount(3);

    // Test helpers
    expect($admin->hasPermission('purchase', 'view'))->toBeTrue();
    expect($admin->hasPermission('purchase', 'add'))->toBeTrue();
    expect($admin->hasPermission('purchase', 'delete'))->toBeFalse();
    expect($admin->hasPermission('sale', 'delete'))->toBeTrue();
    expect($admin->can('purchase.view'))->toBeTrue();
    expect($admin->can('view', 'purchase'))->toBeTrue();
    expect($admin->cannot('purchase.delete'))->toBeTrue();
});
