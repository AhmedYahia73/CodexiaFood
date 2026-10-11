<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\WasteResource;
use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeStock;
use App\Models\Waste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WasteController extends BaseBranchController
{
    public function selectOptions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptions(),
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

        $wastes = Waste::where('branch_id', $branchId)
            ->with(['branch', 'productRecipe', 'material'])
            ->latest()
            ->paginate($perPage);

        return WasteResource::collection($wastes)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'branch_id' => 'sometimes|nullable|integer',
            'product_recipe_id' => 'nullable|exists:product_recipes,id',
            'material_id' => 'nullable|exists:materials,id',
            'count' => 'required|integer|min:1',
        ]);

        if (empty($validated['product_recipe_id']) && empty($validated['material_id'])) {
            return response()->json([
                'status' => false,
                'message' => 'يجب تحديد المادة الخام أو وصفة المنتج المراد تسجيل الهالك لها.',
            ], 422);
        }

        if (! empty($validated['product_recipe_id']) && ! empty($validated['material_id'])) {
            return response()->json([
                'status' => false,
                'message' => 'يجب اختيار إما مادة خام أو وصفة منتج واحدة فقط.',
            ], 422);
        }

        $waste = DB::transaction(function () use ($validated, $branchId) {
            $count = (int) $validated['count'];

            if (! empty($validated['material_id'])) {
                $matStock = MaterialStock::where('material_id', $validated['material_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($matStock?->stock ?? 0);
                if ($availableStock < $count) {
                    abort(response()->json([
                        'status' => false,
                        'message' => "المخزون المتوفر للمادة الخام في هذا الفرع ({$availableStock}) غير كافٍ لتسجيل الهالك المطلوب ({$count}).",
                    ], 422));
                }
                $matStock->decrement('stock', $count);
            }

            if (! empty($validated['product_recipe_id'])) {
                $recStock = ProductRecipeStock::where('product_recipe_id', $validated['product_recipe_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($recStock?->stock ?? 0);
                if ($availableStock < $count) {
                    abort(response()->json([
                        'status' => false,
                        'message' => "المخزون المتوفر لوصفة المنتج في هذا الفرع ({$availableStock}) غير كافٍ لتسجيل الهالك المطلوب ({$count}).",
                    ], 422));
                }
                $recStock->decrement('stock', $count);
            }

            return Waste::create([
                'branch_id' => $branchId,
                'product_recipe_id' => $validated['product_recipe_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'count' => $count,
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste recorded successfully.',
            'data' => new WasteResource($waste->load(['branch', 'productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    public function show(Waste $waste): JsonResponse
    {
        $this->ensureBelongsToBranch($waste);

        return response()->json([
            'status' => true,
            'data' => new WasteResource($waste->load(['branch', 'productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    public function update(Request $request, Waste $waste): JsonResponse
    {
        $this->ensureBelongsToBranch($waste);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'count' => 'required|integer|min:1',
        ]);

        $newCount = (int) $validated['count'];
        $oldCount = (int) $waste->count;
        $diff = $newCount - $oldCount;

        DB::transaction(function () use ($waste, $newCount, $diff, $branchId) {
            if ($diff > 0) {
                if ($waste->material_id) {
                    $matStock = MaterialStock::where('material_id', $waste->material_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();

                    $availableStock = (float) ($matStock?->stock ?? 0);
                    if ($availableStock < $diff) {
                        abort(response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر للمادة الخام في الفرع ({$availableStock}) غير كافٍ لزيادة الهالك بالفارق ({$diff}).",
                        ], 422));
                    }
                    $matStock->decrement('stock', $diff);
                }

                if ($waste->product_recipe_id) {
                    $recStock = ProductRecipeStock::where('product_recipe_id', $waste->product_recipe_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();

                    $availableStock = (float) ($recStock?->stock ?? 0);
                    if ($availableStock < $diff) {
                        abort(response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر لوصفة المنتج في الفرع ({$availableStock}) غير كافٍ لزيادة الهالك بالفارق ({$diff}).",
                        ], 422));
                    }
                    $recStock->decrement('stock', $diff);
                }
            } elseif ($diff < 0) {
                $restoreAmount = abs($diff);

                if ($waste->material_id) {
                    $matStock = MaterialStock::firstOrCreate(
                        ['material_id' => $waste->material_id, 'branch_id' => $branchId],
                        ['stock' => 0]
                    );
                    $matStock->increment('stock', $restoreAmount);
                }

                if ($waste->product_recipe_id) {
                    $recStock = ProductRecipeStock::firstOrCreate(
                        ['product_recipe_id' => $waste->product_recipe_id, 'branch_id' => $branchId],
                        ['stock' => 0]
                    );
                    $recStock->increment('stock', $restoreAmount);
                }
            }

            $waste->update([
                'count' => $newCount,
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste updated successfully.',
            'data' => new WasteResource($waste->fresh(['branch', 'productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    public function destroy(Waste $waste): JsonResponse
    {
        $this->ensureBelongsToBranch($waste);
        $branchId = $this->getBranchId();

        DB::transaction(function () use ($waste, $branchId) {
            $count = (int) $waste->count;

            if ($waste->material_id && $branchId) {
                $matStock = MaterialStock::firstOrCreate(
                    ['material_id' => $waste->material_id, 'branch_id' => $branchId],
                    ['stock' => 0]
                );
                $matStock->increment('stock', $count);
            }

            if ($waste->product_recipe_id && $branchId) {
                $recStock = ProductRecipeStock::firstOrCreate(
                    ['product_recipe_id' => $waste->product_recipe_id, 'branch_id' => $branchId],
                    ['stock' => 0]
                );
                $recStock->increment('stock', $count);
            }

            $waste->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste deleted successfully and stock restored.',
        ]);
    }

    private function getSelectOptions(): array
    {
        $branchId = $this->getBranchId();

        return [
            'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            'materials' => Material::select('id', 'name')->get()->map(function ($item) use ($branchId) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'stock' => (float) $item->stockForBranch($branchId),
                ];
            }),
            'product_recipes' => ProductRecipe::select('id', 'name')->get()->map(function ($item) use ($branchId) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'stock' => (float) $item->stockForBranch($branchId),
                ];
            }),
        ];
    }
}
