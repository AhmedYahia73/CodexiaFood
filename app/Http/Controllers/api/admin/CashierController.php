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
        $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $cashiers = Cashier::with(['branch', 'cashierMan', 'activeStartShift.cashierMan'])
            ->withExists(['startShifts as has_active_shift' => fn ($q) => $q->whereNull('end')])
            ->when($request->query('branch_id'), fn ($q, $bid) => $q->where('branch_id', $bid))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return CashierResource::collection($cashiers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cashier_man_id' => 'sometimes|nullable|exists:cashier_men,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $cashier = Cashier::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier created successfully.',
            'data' => new CashierResource($cashier->load(['branch', 'cashierMan', 'activeStartShift.cashierMan'])),
        ], 201);
    }

    public function show(Cashier $cashier): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new CashierResource($cashier->load(['branch', 'cashierMan', 'activeStartShift.cashierMan'])),
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
            'cashier_man_id' => 'sometimes|nullable|exists:cashier_men,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $cashier->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier updated successfully.',
            'data' => new CashierResource($cashier->fresh(['branch', 'cashierMan', 'activeStartShift.cashierMan'])),
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
