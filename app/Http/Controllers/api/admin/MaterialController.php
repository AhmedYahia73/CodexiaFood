<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\Category;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaterialController extends Controller
{
    /**
     * Get select options for material forms (categories list).
     */
    public function selectOptions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptions($request),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $materials = Material::with('category')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return MaterialResource::collection($materials)->additional([
            'select_options' => $this->getSelectOptions($request),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $material = Material::create([
            'name' => $validated['name'],
            'stock' => $validated['stock'] ?? 0,
            'status' => $validated['status'] ?? true,
            'category_id' => $validated['category_id'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Material created successfully.',
            'data' => new MaterialResource($material->load('category')),
            'select_options' => $this->getSelectOptions($request),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Material $material): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new MaterialResource($material->load('category')),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Material $material): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }
        if ($request->has('stock')) {
            $dataToUpdate['stock'] = $validated['stock'] ?? 0;
        }
        if ($request->has('status')) {
            $dataToUpdate['status'] = $validated['status'] ?? true;
        }
        if ($request->has('category_id')) {
            $dataToUpdate['category_id'] = $validated['category_id'] ?? null;
        }

        if (! empty($dataToUpdate)) {
            $material->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Material updated successfully.',
            'data' => new MaterialResource($material->fresh(['category'])),
            'select_options' => $this->getSelectOptions($request),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return response()->json([
            'status' => true,
            'message' => 'Material deleted successfully.',
        ]);
    }

    /**
     * Shared category select options for materials.
     */
    private function getSelectOptions(?Request $request = null): array
    {
        $query = Category::query();
        $type = $request?->query('type');

        if ($type && $type !== 'all') {
            $query->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhereNull('type');
            });
        } elseif (! $type) {
            if (Category::where('type', 'material')->exists()) {
                $query->where(function ($q) {
                    $q->where('type', 'material')->orWhereNull('type');
                });
            }
        }

        return [
            'categories' => $query->select('id', 'name', 'type')->get(),
        ];
    }

    /**
     * Normalize name field if passed as string or JSON string.
     */
    private function normalizeMultilingualName(Request $request): void
    {
        if ($request->has('name') && is_string($request->input('name'))) {
            $decoded = json_decode($request->input('name'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['name' => $decoded]);
            } else {
                $request->merge([
                    'name' => [
                        'en' => $request->input('name'),
                        'ar' => $request->input('name'),
                    ],
                ]);
            }
        }
    }
}
