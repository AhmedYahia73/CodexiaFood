<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashierManResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierManController extends Controller
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
        return [
            'branches' => Branch::select('id', 'name')->get(),
            'cashiers' => Cashier::select('id', 'name')->get(),
            'shifts' => Shift::select('id', 'name', 'branch_id', 'start_time', 'end_time', 'is_tomorrow')->get(),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $cashierMen = CashierMan::with(['branch', 'cashier', 'shift'])->latest()->paginate($request->get('per_page', 15));

        return CashierManResource::collection($cashierMen)->additional([
            'select_options' => $this->getSelectOptionsData(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'cashier_id' => 'nullable|exists:cashiers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

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
        return response()->json([
            'status' => true,
            'data' => new CashierManResource($cashierMan->load(['branch', 'cashier', 'shift'])),
            'select_options' => $this->getSelectOptionsData(),
        ]);
    }

    public function update(Request $request, CashierMan $cashierMan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'password' => 'nullable|string|min:6',
            'cashier_id' => 'nullable|exists:cashiers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

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
        $cashierMan->delete();

        return response()->json([
            'status' => true,
            'message' => 'CashierMan deleted successfully.',
        ]);
    }
}
