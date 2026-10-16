<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\CashierManResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierManController extends BaseBranchController
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptionsData(),
        ]);
    }

    private function getSelectOptionsData(): array
    {
        $branchId = $this->getBranchId();

        return [
            'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            'cashiers' => Cashier::where('branch_id', $branchId)->select('id', 'name')->get(),
            'shifts' => Shift::where('branch_id', $branchId)->select('id', 'name', 'branch_id', 'start_time', 'end_time', 'is_tomorrow')->get(),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $cashierMen = CashierMan::where('branch_id', $branchId)
            ->with(['branch', 'cashier', 'shift'])
            ->latest()
            ->paginate($perPage);

        return CashierManResource::collection($cashierMen)->additional([
            'select_options' => $this->getSelectOptionsData(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'cashier_id' => 'sometimes|nullable|exists:cashiers,id',
            'branch_id' => 'sometimes|nullable|integer',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        if (! empty($validated['cashier_id'])) {
            $cashier = Cashier::find($validated['cashier_id']);
            if ($cashier && (int) $cashier->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cashier must belong to your branch.',
                ], 422);
            }
        }

        if (! empty($validated['shift_id'])) {
            $shift = Shift::find($validated['shift_id']);
            if ($shift && (int) $shift->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift must belong to your branch.',
                ], 422);
            }
        }

        $validated['branch_id'] = $branchId;
        $cashierMan = CashierMan::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'CashierMan created successfully.',
            'data' => new CashierManResource($cashierMan->load(['branch', 'cashier', 'shift'])),
            'select_options' => $this->getSelectOptionsData(),
        ], 201);
    }

    public function show(CashierMan $cashierMan): JsonResponse
    {
        $this->ensureBelongsToBranch($cashierMan);

        return response()->json([
            'status' => true,
            'data' => new CashierManResource($cashierMan->load(['branch', 'cashier', 'shift'])),
            'select_options' => $this->getSelectOptionsData(),
        ]);
    }

    public function update(Request $request, CashierMan $cashierMan): JsonResponse
    {
        $this->ensureBelongsToBranch($cashierMan);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'password' => 'nullable|string|min:6',
            'cashier_id' => 'sometimes|nullable|exists:cashiers,id',
            'branch_id' => 'sometimes|nullable|integer',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        if (! empty($validated['cashier_id'])) {
            $cashier = Cashier::find($validated['cashier_id']);
            if ($cashier && (int) $cashier->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cashier must belong to your branch.',
                ], 422);
            }
        }

        if (! empty($validated['shift_id'])) {
            $shift = Shift::find($validated['shift_id']);
            if ($shift && (int) $shift->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift must belong to your branch.',
                ], 422);
            }
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['branch_id'] = $branchId;
        $cashierMan->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'CashierMan updated successfully.',
            'data' => new CashierManResource($cashierMan->fresh(['branch', 'cashier', 'shift'])),
            'select_options' => $this->getSelectOptionsData(),
        ]);
    }

    public function destroy(CashierMan $cashierMan): JsonResponse
    {
        $this->ensureBelongsToBranch($cashierMan);
        $cashierMan->delete();

        return response()->json([
            'status' => true,
            'message' => 'CashierMan deleted successfully.',
        ]);
    }
}
