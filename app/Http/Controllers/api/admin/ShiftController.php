<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
                'shifts' => Shift::select('id', 'name', 'branch_id', 'start_time', 'end_time', 'is_tomorrow')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $shifts = Shift::with('branch')->latest()->paginate($request->get('per_page', 15));

        return ShiftResource::collection($shifts)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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
            'branch_id' => 'required|exists:branches,id',
        ]);

        $validated['is_tomorrow'] = $validated['end_time'] < $validated['start_time'];

        $shift = Shift::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shift created successfully.',
            'data' => new ShiftResource($shift->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new ShiftResource($shift->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
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
            'branch_id' => 'sometimes|required|exists:branches,id',
        ]);

        $startTime = $validated['start_time'] ?? $shift->start_time;
        $endTime = $validated['end_time'] ?? $shift->end_time;
        $validated['is_tomorrow'] = $endTime < $startTime;

        $shift->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shift updated successfully.',
            'data' => new ShiftResource($shift->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shift deleted successfully.',
        ]);
    }
}
