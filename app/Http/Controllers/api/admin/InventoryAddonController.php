<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryAddonResource;
use App\Http\Resources\InventoryDetailResource;
use App\Http\Resources\InventoryListResource;
use App\Models\Addon;
use App\Models\AddonStock;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAddon;
use App\Models\Unit;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryAddonController extends Controller
{
    /**
     * Get select options for addon inventory (branches, units).
     */
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
                'units' => Unit::select('id', 'name', 'value')->get(),
            ],
        ]);
    }

    /**
     * Create a new addon inventory and populate all addons with current branch stock.
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

            $addons = Addon::all();

            foreach ($addons as $addon) {
                $addonStock = AddonStock::where('addon_id', $addon->id)
                    ->where('branch_id', $branchId)
                    ->first();
                $stock = (float) ($addonStock?->stock ?? 0);
                $unitId = $addonStock?->unit_id;

                InventoryAddon::create([
                    'inventory_id' => $inventory->id,
                    'addon_id' => $addon->id,
                    'stock' => $stock,
                    'actual_stock' => $stock,
                    'unit_id' => $unitId,
                ]);
            }

            return $inventory;
        });

        $inventory->load(['branch', 'inventoryAddons.addon', 'inventoryAddons.unit']);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء جرد الإضافات بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ], 201);
    }

    /**
     * List inventories with status 'pending' that have addon items.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::where('status', 'pending')
            ->whereHas('inventoryAddons')
            ->with('branch')
            ->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $inventories = $query->paginate($request->get('per_page', 15));

        return InventoryListResource::collection($inventories);
    }

    /**
     * List inventories with status != 'pending' (approved or rejected) that have addon items.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $query = Inventory::where('status', '!=', 'pending')
            ->whereHas('inventoryAddons')
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
     * Show addon inventory details and its addon items.
     */
    public function show(Inventory $inventory): JsonResponse
    {
        $inventory->load(['branch', 'inventoryAddons.addon', 'inventoryAddons.unit']);

        return response()->json([
            'status' => true,
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Update actual stock for a single inventory addon item.
     */
    public function updateItem(Request $request, InventoryAddon $item): JsonResponse
    {
        $validated = $request->validate([
            'actual_stock' => 'required|numeric|min:0',
            'unit_id' => 'sometimes|nullable|integer|exists:units,id',
        ]);

        if ($item->inventory?->status !== 'pending') {
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
            'message' => 'تم تحديث الرصيد الفعلي للإضافة بنجاح',
            'data' => new InventoryAddonResource($item->load(['addon', 'unit'])),
        ]);
    }

    /**
     * Batch update actual stocks for multiple inventory addon items.
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
            'items.*.id' => 'required|integer|exists:inventory_addons,id',
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

                InventoryAddon::where('id', $itemData['id'])
                    ->where('inventory_id', $inventory->id)
                    ->update($updateData);
            }
        });

        $inventory->load(['branch', 'inventoryAddons.addon', 'inventoryAddons.unit']);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الأرصدة الفعلية بنجاح',
            'data' => new InventoryDetailResource($inventory),
        ]);
    }

    /**
     * Change inventory status (approve or reject).
     * If approved, branch AddonStock is overwritten with converted actual_stock.
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
                $inventory->load('inventoryAddons');

                foreach ($inventory->inventoryAddons as $item) {
                    $stockModel = AddonStock::firstOrCreate(
                        [
                            'branch_id' => $inventory->branch_id,
                            'addon_id' => $item->addon_id,
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

        $inventory->load(['branch', 'inventoryAddons.addon', 'inventoryAddons.unit']);

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
