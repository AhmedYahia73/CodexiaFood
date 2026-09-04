<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HallResource;
use App\Models\Branch;
use App\Models\Hall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HallController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $halls = Hall::with('branch')->latest()->paginate($request->get('per_page', 15));

        return HallResource::collection($halls)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        $hall = Hall::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall created successfully.',
            'data' => new HallResource($hall->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Hall $hall): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new HallResource($hall->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Hall $hall): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        $hall->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Hall updated successfully.',
            'data' => new HallResource($hall->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Hall $hall): JsonResponse
    {
        $hall->delete();

        return response()->json([
            'status' => true,
            'message' => 'Hall deleted successfully.',
        ]);
    }
}
