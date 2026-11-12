<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Get available permissions dictionary with their supported actions.
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => PermissionService::getAvailablePermissions(),
        ]);
    }

    /**
     * Select options for roles and permissions.
     */
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'roles' => Role::select('id', 'name')->get(),
                'permissions' => PermissionService::getAvailablePermissions(),
            ],
        ]);
    }

    /**
     * Display a listing of the roles.
     * Only displays roles without permissions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Search by role name.
             *
             * @var string|null
             *
             * @example "Manager"
             */
            'name' => 'sometimes|nullable|string|max:255',

            /**
             * Page number.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $query = Role::query();

        if (! empty($validated['name'])) {
            $query->where('name', 'like', '%'.$validated['name'].'%');
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $roles = $query->latest('id')->paginate($perPage);

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created role with its permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Role name.
             *
             * @var string
             *
             * @example "Supervisor"
             */
            'name' => 'required|string|max:255|unique:roles,name',

            /**
             * Permissions for the role. Can be an associative map, list of objects, or list of strings.
             *
             * @var array|null
             *
             * @example {"purchase": ["all"], "category": ["view", "add"]}
             */
            'permissions' => 'nullable|array',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        if (array_key_exists('permissions', $validated) && ! empty($validated['permissions'])) {
            $this->permissionService->syncRolePermissions($role, $validated['permissions']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    /**
     * Display the specified role with its permissions.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new RoleResource($role->load('permissions')),
            'select_options' => [
                'permissions' => PermissionService::getAvailablePermissions(),
            ],
        ]);
    }

    /**
     * Update the specified role and sync its permissions.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Role name.
             *
             * @var string|null
             *
             * @example "Updated Supervisor"
             */
            'name' => 'sometimes|required|string|max:255|unique:roles,name,'.$role->id,

            /**
             * Permissions to sync. Can be an associative map, list of objects, or list of strings.
             *
             * @var array|null
             *
             * @example {"purchase": ["view", "add"]}
             */
            'permissions' => 'sometimes|nullable|array',
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (array_key_exists('permissions', $validated)) {
            $this->permissionService->syncRolePermissions($role, $validated['permissions'] ?? []);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role->fresh(['permissions'])),
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }
}
