<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashierResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
                'cashier_men' => CashierMan::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $cashiers = Cashier::with('branch')->latest()->paginate($request->get('per_page', 15));

        return CashierResource::collection($cashiers)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'cashier_men' => CashierMan::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cashier_man_id' => 'nullable|exists:cashier_men,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $cashier = Cashier::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier created successfully.',
            'data' => new CashierResource($cashier->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'cashier_men' => CashierMan::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Cashier $cashier): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new CashierResource($cashier->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'cashier_men' => CashierMan::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Cashier $cashier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'cashier_man_id' => 'nullable|exists:cashier_men,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $cashier->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier updated successfully.',
            'data' => new CashierResource($cashier->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
                'cashier_men' => CashierMan::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Cashier $cashier): JsonResponse
    {
        $cashier->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cashier deleted successfully.',
        ]);
    }
}
