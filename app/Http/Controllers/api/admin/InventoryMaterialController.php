<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryDetailResource;
use App\Http\Resources\InventoryListResource;
use App\Http\Resources\InventoryMaterialResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMaterial;
use App\Models\Material;
use App\Models\MaterialStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryMaterialController extends Controller
{
    /**
     * Get select options for material inventory (branches).
     */
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    /**
     * Create a new material inventory and populate all materials with current branch stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        $branchId = (int) $validated['branch_id'];

        $inventory = DB::transaction(function () use ($validated, $branchId): Inventory {
            $inventory = Inventory::create([
                'name' => $validated['name'],
                'branch_id' => $branchId,
                'status' => 'pending',
            ]);

            $materials = Material::all();

            foreach ($materials as $material) {
                $stock = (float) (MaterialStock::where('material_id', $material->id)
                    ->where('branch_id', $branchId)
                    ->value('stock') ?? 0);

                InventoryMaterial::create([
                    'inventory_id' => $inventory->id,
                    'material_id' => $material->id,
                    'stock' => $stock,
                    'actual_stock' => $stock,
                ]);
            }

            return $inventory;
        });

        $inventory->load(['branch', 'inventoryMaterials.material']);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء جرد المواد الخام بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ], 201);
    }

    /**
     * List inventories with status 'pending' that have material items.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::where('status', 'pending')
            ->whereHas('inventoryMaterials')
            ->with('branch')
            ->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $inventories = $query->paginate($request->get('per_page', 15));

        return InventoryListResource::collection($inventories);
    }

    /**
     * List inventories with status != 'pending' (approved or rejected) that have material items.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::where('status', '!=', 'pending')
            ->whereHas('inventoryMaterials')
            ->with('branch')
            ->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $inventories = $query->paginate($request->get('per_page', 15));

        return InventoryListResource::collection($inventories);
    }

    /**
     * Show material inventory details and its material items.
     */
    public function show(Inventory $inventory): JsonResponse
    {
        $inventory->load(['branch', 'inventoryMaterials.material']);

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
        $validated = $request->validate([
            'actual_stock' => 'required|numeric|min:0',
        ]);

        if ($item->inventory?->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الجرد بعد اعتماده أو رفضه',
            ], 422);
        }

        $item->update([
            'actual_stock' => $validated['actual_stock'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الرصيد الفعلي للمادة بنجاح',
            'data' => new InventoryMaterialResource($item->load('material')),
        ]);
    }

    /**
     * Batch update actual stocks for multiple inventory material items.
     */
    public function updateActualStocks(Request $request, Inventory $inventory): JsonResponse
    {
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
        ]);

        DB::transaction(function () use ($validated, $inventory): void {
            foreach ($validated['items'] as $itemData) {
                InventoryMaterial::where('id', $itemData['id'])
                    ->where('inventory_id', $inventory->id)
                    ->update([
                        'actual_stock' => $itemData['actual_stock'],
                    ]);
            }
        });

        $inventory->load(['branch', 'inventoryMaterials.material']);

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

        DB::transaction(function () use ($inventory, $newStatus): void {
            if ($newStatus === 'approve') {
                $inventory->load('inventoryMaterials');

                foreach ($inventory->inventoryMaterials as $item) {
                    MaterialStock::updateOrCreate(
                        [
                            'branch_id' => $inventory->branch_id,
                            'material_id' => $item->material_id,
                        ],
                        [
                            'stock' => $item->actual_stock,
                        ]
                    );
                }
            }

            $inventory->update([
                'status' => $newStatus,
            ]);
        });

        $inventory->load(['branch', 'inventoryMaterials.material']);

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
