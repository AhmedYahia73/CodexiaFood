<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HallTableResource;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\HallTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HallTableController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
                'halls' => Hall::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $hallTables = HallTable::with(['branch', 'hall'])->latest()->paginate($request->get('per_page', 15));

        return HallTableResource::collection($hallTables)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'halls' => Hall::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'hall_id' => 'nullable|exists:halls,id',
            'status' => 'nullable|boolean',
        ]);

        $hallTable = HallTable::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall table created successfully.',
            'data' => new HallTableResource($hallTable->load(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'halls' => Hall::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(HallTable $hallTable): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new HallTableResource($hallTable->load(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'halls' => Hall::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, HallTable $hallTable): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'hall_id' => 'nullable|exists:halls,id',
            'status' => 'nullable|boolean',
        ]);

        $hallTable->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall table updated successfully.',
            'data' => new HallTableResource($hallTable->fresh(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'halls' => Hall::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(HallTable $hallTable): JsonResponse
    {
        $hallTable->delete();

        return response()->json([
            'status' => true,
            'message' => 'Hall table deleted successfully.',
        ]);
    }
}
