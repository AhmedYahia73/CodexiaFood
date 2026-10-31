<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\InventoryDetailResource;
use App\Http\Resources\InventoryListResource;
use App\Http\Resources\InventoryMaterialResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMaterial;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Unit;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryMaterialController extends BaseBranchController
{
    /**
     * Get select options for material inventory (current branch).
     */
    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'units' => Unit::select('id', 'name', 'value')->get(),
            ],
        ]);
    }

    /**
     * Create a new material inventory and populate all materials with current branch stock.
     */
    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $inventory = DB::transaction(function () use ($validated, $branchId): Inventory {
            $inventory = Inventory::create([
                'name' => $validated['name'],
                'branch_id' => $branchId,
                'status' => 'pending',
            ]);

            $materials = Material::all();

            foreach ($materials as $material) {
                $matStock = MaterialStock::where('material_id', $material->id)
                    ->where('branch_id', $branchId)
                    ->first();
                $stock = (float) ($matStock?->stock ?? 0);
                $unitId = $matStock?->unit_id;

                InventoryMaterial::create([
                    'inventory_id' => $inventory->id,
                    'material_id' => $material->id,
                    'stock' => $stock,
                    'actual_stock' => $stock,
                    'unit_id' => $unitId,
                ]);
            }

            return $inventory;
        });

        $inventory->load(['branch', 'inventoryMaterials.material', 'inventoryMaterials.unit']);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء جرد المواد الخام بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ], 201);
    }

    /**
     * List inventories with status 'pending' that have material items for this branch.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $inventories = Inventory::where('branch_id', $branchId)
            ->where('status', 'pending')
            ->whereHas('inventoryMaterials')
            ->with('branch')
            ->latest('id')
            ->paginate($perPage);

        return InventoryListResource::collection($inventories);
    }

    /**
     * List inventories with status != 'pending' (approved or rejected) for this branch.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:approve,reject',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $query = Inventory::where('branch_id', $branchId)
            ->where('status', '!=', 'pending')
            ->whereHas('inventoryMaterials')
            ->with('branch')
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $inventories = $query->paginate($perPage);

        return InventoryListResource::collection($inventories);
    }

    /**
     * Show material inventory details and its material items.
     */
    public function show(Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);
        $inventory->load(['branch', 'inventoryMaterials.material', 'inventoryMaterials.unit']);

        return response()->json([
            'status' => true,
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Update actual stock for a single inventory material item.
     */
    public function updateItem(Request $request, InventoryMaterial $item): JsonResponse
    {
        $item->load('inventory');
        if (! $item->inventory || (int) $item->inventory->branch_id !== $this->getBranchId()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action: item does not belong to your branch.',
            ], 403);
        }

        $validated = $request->validate([
            'actual_stock' => 'required|numeric|min:0',
            'unit_id' => 'sometimes|nullable|integer|exists:units,id',
        ]);

        if ($item->inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الجرد بعد اعتماده أو رفضه',
            ], 422);
        }

        $dataToUpdate = [
            'actual_stock' => $validated['actual_stock'],
        ];
        if (array_key_exists('unit_id', $validated)) {
            $dataToUpdate['unit_id'] = $validated['unit_id'];
        }

        $item->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الرصيد الفعلي للمادة بنجاح',
            'data' => new InventoryMaterialResource($item->load(['material', 'unit'])),
        ]);
    }

    /**
     * Batch update actual stocks for multiple inventory material items.
     */
    public function updateActualStocks(Request $request, Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);

        if ($inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الجرد بعد اعتماده أو رفضه',
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:inventory_materials,id',
            'items.*.actual_stock' => 'required|numeric|min:0',
            'items.*.unit_id' => 'sometimes|nullable|integer|exists:units,id',
        ]);

        DB::transaction(function () use ($validated, $inventory): void {
            foreach ($validated['items'] as $itemData) {
                $updateData = [
                    'actual_stock' => $itemData['actual_stock'],
                ];
                if (array_key_exists('unit_id', $itemData)) {
                    $updateData['unit_id'] = $itemData['unit_id'];
                }

                InventoryMaterial::where('id', $itemData['id'])
                    ->where('inventory_id', $inventory->id)
                    ->update($updateData);
            }
        });

        $inventory->load(['branch', 'inventoryMaterials.material', 'inventoryMaterials.unit']);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الأرصدة الفعلية بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Change inventory status (approve or reject).
     * If approved, branch MaterialStock is overwritten with actual_stock.
     */
    public function changeStatus(Request $request, Inventory $inventory): JsonResponse
    {
        $this->ensureBelongsToBranch($inventory);

        $validated = $request->validate([
            'status' => 'required|in:approve,reject',
        ]);

        if ($inventory->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'تم اتخاذ إجراء على هذا الجرد مسبقاً ولا يمكن تغييره مجدداً',
            ], 422);
        }

        $newStatus = $validated['status'];
        $branchId = $this->getBranchId();

        DB::transaction(function () use ($inventory, $newStatus, $branchId): void {
            if ($newStatus === 'approve') {
                $inventory->load('inventoryMaterials');

                foreach ($inventory->inventoryMaterials as $item) {
                    $stockModel = MaterialStock::firstOrCreate(
                        [
                            'branch_id' => $branchId,
                            'material_id' => $item->material_id,
                        ],
                        [
                            'stock' => 0,
                            'unit_id' => $item->unit_id,
                        ]
                    );

                    if (! $stockModel->unit_id && $item->unit_id) {
                        $stockModel->update(['unit_id' => $item->unit_id]);
                    }

                    $convertedStock = UnitConversionService::convert((float) $item->actual_stock, $item->unit_id, $stockModel->unit_id);
                    $stockModel->update(['stock' => $convertedStock]);
                }
            }

            $inventory->update([
                'status' => $newStatus,
            ]);
        });

        $inventory->load(['branch', 'inventoryMaterials.material', 'inventoryMaterials.unit']);

        $message = $newStatus === 'approve'
            ? 'تم اعتماد الجرد وتحديث أرصدة مخزون الفرع بنجاح'
            : 'تم رفض الجرد بنجاح';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => new InventoryDetailResource($inventory),
        ]);
    }
}
