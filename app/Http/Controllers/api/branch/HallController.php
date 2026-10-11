<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\HallResource;
use App\Models\Branch;
use App\Models\Hall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HallController extends BaseBranchController
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

        $halls = Hall::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return HallResource::collection($halls)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $validated['branch_id'] = $branchId;
        $hall = Hall::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall created successfully.',
            'data' => new HallResource($hall->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Hall $hall): JsonResponse
    {
        $this->ensureBelongsToBranch($hall);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new HallResource($hall->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Hall $hall): JsonResponse
    {
        $this->ensureBelongsToBranch($hall);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $validated['branch_id'] = $branchId;
        $hall->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall updated successfully.',
            'data' => new HallResource($hall->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Hall $hall): JsonResponse
    {
        $this->ensureBelongsToBranch($hall);
        $hall->delete();

        return response()->json([
            'status' => true,
            'message' => 'Hall deleted successfully.',
        ]);
    }
}
