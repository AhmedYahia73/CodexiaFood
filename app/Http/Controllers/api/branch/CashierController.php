<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\CashierResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierController extends BaseBranchController
{
    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'cashier_men' => CashierMan::where('branch_id', $branchId)->select('id', 'name')->get(),
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

        $cashiers = Cashier::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return CashierResource::collection($cashiers)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'cashier_men' => CashierMan::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cashier_man_id' => 'sometimes|nullable|exists:cashier_men,id',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        if (! empty($validated['cashier_man_id'])) {
            $cashierMan = CashierMan::find($validated['cashier_man_id']);
            if ($cashierMan && (int) $cashierMan->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cashier man must belong to your branch.',
                ], 422);
            }
        }

        $validated['branch_id'] = $branchId;
        $cashier = Cashier::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier created successfully.',
            'data' => new CashierResource($cashier->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'cashier_men' => CashierMan::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Cashier $cashier): JsonResponse
    {
        $this->ensureBelongsToBranch($cashier);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new CashierResource($cashier->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'cashier_men' => CashierMan::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Cashier $cashier): JsonResponse
    {
        $this->ensureBelongsToBranch($cashier);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'cashier_man_id' => 'sometimes|nullable|exists:cashier_men,id',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        if (! empty($validated['cashier_man_id'])) {
            $cashierMan = CashierMan::find($validated['cashier_man_id']);
            if ($cashierMan && (int) $cashierMan->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cashier man must belong to your branch.',
                ], 422);
            }
        }

        $validated['branch_id'] = $branchId;
        $cashier->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cashier updated successfully.',
            'data' => new CashierResource($cashier->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'cashier_men' => CashierMan::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Cashier $cashier): JsonResponse
    {
        $this->ensureBelongsToBranch($cashier);
        $cashier->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cashier deleted successfully.',
        ]);
    }
}
