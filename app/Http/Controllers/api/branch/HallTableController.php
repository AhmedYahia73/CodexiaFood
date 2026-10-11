<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Controllers\api\branch\BaseBranchController;
use App\Http\Resources\HallTableResource;
use App\Models\Branch;
use App\Models\BusinessSetup;
use App\Models\Hall;
use App\Models\HallTable;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HallTableController extends BaseBranchController
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'halls' => Hall::where('branch_id', $branchId)->select('id', 'name', 'branch_id')->get(),
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

        $hallTables = HallTable::where('branch_id', $branchId)
            ->with(['branch', 'hall'])
            ->latest()
            ->paginate($perPage);

        return HallTableResource::collection($hallTables)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'halls' => Hall::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
            'hall_id' => 'required|exists:halls,id',
            'status' => 'required|boolean', 
        ]);

        $hall = Hall::find($validated['hall_id']);
        if ($hall && (int) $hall->branch_id !== $branchId) {
            return response()->json([
                'status' => false,
                'message' => 'The selected hall does not belong to your branch.',
            ], 422);
        }

        $validated['branch_id'] = $branchId;
        $validated['base_url'] = BusinessSetup::first()?->table_order_url ?? $request->get('base_url', '');
        $hallTable = HallTable::create($validated);
        $this->qrCodeService->generateForTable($hallTable, $request);

        return response()->json([
            'status' => true,
            'message' => 'Hall table created successfully.',
            'data' => new HallTableResource($hallTable->fresh(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'halls' => Hall::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(HallTable $hallTable): JsonResponse
    {
        $this->ensureBelongsToBranch($hallTable);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new HallTableResource($hallTable->load(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'halls' => Hall::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, HallTable $hallTable): JsonResponse
    {
        $this->ensureBelongsToBranch($hallTable);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
            'hall_id' => 'nullable|exists:halls,id',
            'status' => 'nullable|boolean',
            'base_url' => 'required|string|max:255',
        ]);

        if (! empty($validated['hall_id'])) {
            $hall = Hall::find($validated['hall_id']);
            if ($hall && (int) $hall->branch_id !== $branchId) {
                return response()->json([
                    'status' => false,
                    'message' => 'The selected hall does not belong to your branch.',
                ], 422);
            }
        }

        $validated['branch_id'] = $branchId;
        $hallTable->update($validated);

        if (! $hallTable->qr) {
            $this->qrCodeService->generateForTable($hallTable, $request);
        }

        return response()->json([
            'status' => true,
            'message' => 'Hall table updated successfully.',
            'data' => new HallTableResource($hallTable->fresh(['branch', 'hall'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'halls' => Hall::where('branch_id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(HallTable $hallTable): JsonResponse
    {
        $this->ensureBelongsToBranch($hallTable);
        $hallTable->delete();

        return response()->json([
            'status' => true,
            'message' => 'Hall table deleted successfully.',
        ]);
    }
}
