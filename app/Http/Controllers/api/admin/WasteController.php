<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WasteResource;
use App\Models\Material;
use App\Models\ProductRecipe;
use App\Models\Waste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WasteController extends Controller
{
    /**
     * Get select options for waste forms (materials and product recipes).
     */
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $wastes = Waste::with(['productRecipe', 'material'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return WasteResource::collection($wastes)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage and deduct from stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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

        $waste = DB::transaction(function () use ($validated) {
            $count = (int) $validated['count'];

            if (! empty($validated['material_id'])) {
                $material = Material::lockForUpdate()->findOrFail($validated['material_id']);
                if ($material->stock < $count) {
                    abort(response()->json([
                        'status' => false,
                        'message' => "المخزون المتوفر للمادة الخام ({$material->stock}) غير كافٍ لتسجيل الهالك المطلوب ({$count}).",
                    ], 422));
                }
                $material->decrement('stock', $count);
            }

            if (! empty($validated['product_recipe_id'])) {
                $recipe = ProductRecipe::lockForUpdate()->findOrFail($validated['product_recipe_id']);
                if ($recipe->stock < $count) {
                    abort(response()->json([
                        'status' => false,
                        'message' => "المخزون المتوفر لوصفة المنتج ({$recipe->stock}) غير كافٍ لتسجيل الهالك المطلوب ({$count}).",
                    ], 422));
                }
                $recipe->decrement('stock', $count);
            }

            return Waste::create([
                'product_recipe_id' => $validated['product_recipe_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'count' => $count,
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste recorded successfully.',
            'data' => new WasteResource($waste->load(['productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Waste $waste): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new WasteResource($waste->load(['productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage and adjust stock by the difference (only count is updatable).
     */
    public function update(Request $request, Waste $waste): JsonResponse
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1',
        ]);

        $newCount = (int) $validated['count'];
        $oldCount = (int) $waste->count;
        $diff = $newCount - $oldCount;

        DB::transaction(function () use ($waste, $newCount, $diff) {
            if ($diff > 0) {
                if ($waste->material_id) {
                    $material = Material::lockForUpdate()->findOrFail($waste->material_id);
                    if ($material->stock < $diff) {
                        abort(response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر للمادة الخام ({$material->stock}) غير كافٍ لزيادة الهالك بالفارق ({$diff}).",
                        ], 422));
                    }
                    $material->decrement('stock', $diff);
                }

                if ($waste->product_recipe_id) {
                    $recipe = ProductRecipe::lockForUpdate()->findOrFail($waste->product_recipe_id);
                    if ($recipe->stock < $diff) {
                        abort(response()->json([
                            'status' => false,
                            'message' => "المخزون المتوفر لوصفة المنتج ({$recipe->stock}) غير كافٍ لزيادة الهالك بالفارق ({$diff}).",
                        ], 422));
                    }
                    $recipe->decrement('stock', $diff);
                }
            } elseif ($diff < 0) {
                $restoreAmount = abs($diff);

                if ($waste->material_id) {
                    $material = Material::lockForUpdate()->findOrFail($waste->material_id);
                    $material->increment('stock', $restoreAmount);
                }

                if ($waste->product_recipe_id) {
                    $recipe = ProductRecipe::lockForUpdate()->findOrFail($waste->product_recipe_id);
                    $recipe->increment('stock', $restoreAmount);
                }
            }

            $waste->update([
                'count' => $newCount,
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste updated successfully.',
            'data' => new WasteResource($waste->fresh(['productRecipe', 'material'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage and restore stock.
     */
    public function destroy(Waste $waste): JsonResponse
    {
        DB::transaction(function () use ($waste) {
            $count = (int) $waste->count;

            if ($waste->material_id) {
                $material = Material::lockForUpdate()->find($waste->material_id);
                $material?->increment('stock', $count);
            }

            if ($waste->product_recipe_id) {
                $recipe = ProductRecipe::lockForUpdate()->find($waste->product_recipe_id);
                $recipe?->increment('stock', $count);
            }

            $waste->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Waste deleted successfully and stock restored.',
        ]);
    }

    /**
     * Shared select options for wastes.
     */
    private function getSelectOptions(): array
    {
        return [
            'materials' => Material::select('id', 'name', 'stock')->get(),
            'product_recipes' => ProductRecipe::select('id', 'name', 'stock')->get(),
        ];
    }
}
