<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'roles' => Role::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $admins = Admin::with('role')->latest()->paginate($request->get('per_page', 15));

        return AdminResource::collection($admins)->additional([
            'select_options' => [
                'roles' => Role::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $admin = Admin::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Admin created successfully.',
            'data' => new AdminResource($admin->load('role')),
            'select_options' => [
                'roles' => Role::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Admin $admin): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new AdminResource($admin->load('role')),
            'select_options' => [
                'roles' => Role::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'password' => 'nullable|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $admin->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Admin updated successfully.',
            'data' => new AdminResource($admin->fresh(['role'])),
            'select_options' => [
                'roles' => Role::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Admin $admin): JsonResponse
    {
        $admin->delete();

        return response()->json([
            'status' => true,
            'message' => 'Admin deleted successfully.',
        ]);
    }
}
