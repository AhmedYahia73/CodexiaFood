<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\ShiftResource;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends BaseBranchController
{
    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'shifts' => Shift::where('branch_id', $branchId)->select('id', 'name', 'branch_id', 'start_time', 'end_time', 'is_tomorrow')->get(),
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

        $shifts = Shift::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return ShiftResource::collection($shifts)->additional([
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
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $validated['branch_id'] = $branchId;
        $validated['is_tomorrow'] = $validated['end_time'] < $validated['start_time'];

        $shift = Shift::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shift created successfully.',
            'data' => new ShiftResource($shift->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->ensureBelongsToBranch($shift);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new ShiftResource($shift->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->ensureBelongsToBranch($shift);
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
            'start_time' => 'sometimes|required|string',
            'end_time' => 'sometimes|required|string',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $validated['branch_id'] = $branchId;
        $startTime = $validated['start_time'] ?? $shift->start_time;
        $endTime = $validated['end_time'] ?? $shift->end_time;
        $validated['is_tomorrow'] = $endTime < $startTime;

        $shift->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shift updated successfully.',
            'data' => new ShiftResource($shift->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->ensureBelongsToBranch($shift);
        $shift->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shift deleted successfully.',
        ]);
    }
}
