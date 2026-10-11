<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\KitchenResource;
use App\Models\Branch;
use App\Models\Kitchen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class KitchenController extends BaseBranchController
{
    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $kitchens = Kitchen::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return KitchenResource::collection($kitchens)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        if (is_string($request->input('name'))) {
            $request->merge([
                'name' => [
                    'en' => $request->input('name'),
                    'ar' => $request->input('name'),
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'user_name' => 'nullable|string|max:255|unique:kitchens,user_name',
            'password' => 'required|string|min:6',
            'branch_id' => 'sometimes|nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $validated['branch_id'] = $branchId;
        $kitchen = Kitchen::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Kitchen created successfully.',
            'data' => new KitchenResource($kitchen->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Kitchen $kitchen): JsonResponse
    {
        $this->ensureBelongsToBranch($kitchen);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new KitchenResource($kitchen->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Kitchen $kitchen): JsonResponse
    {
        $this->ensureBelongsToBranch($kitchen);
        $branchId = $this->getBranchId();

        if (is_string($request->input('name'))) {
            $request->merge([
                'name' => [
                    'en' => $request->input('name'),
                    'ar' => $request->input('name'),
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'user_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('kitchens', 'user_name')->ignore($kitchen->id),
            ],
            'password' => 'nullable|string|min:6',
            'branch_id' => 'sometimes|nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['branch_id'] = $branchId;
        $kitchen->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Kitchen updated successfully.',
            'data' => new KitchenResource($kitchen->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Kitchen $kitchen): JsonResponse
    {
        $this->ensureBelongsToBranch($kitchen);
        $kitchen->delete();

        return response()->json([
            'status' => true,
            'message' => 'Kitchen deleted successfully.',
        ]);
    }
}
