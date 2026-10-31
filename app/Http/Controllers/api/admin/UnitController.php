<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    /**
     * Get select options for unit forms and dropdowns.
     */
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Display a listing of units with pagination and search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Page number.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',
            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',
            /**
             * Search keyword for unit name.
             *
             * @var string|null
             *
             * @example "kg"
             */
            'search' => 'sometimes|nullable|string|max:255',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Unit::latest();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $units = $query->paginate($perPage);

        return UnitResource::collection($units)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            /**
             * Multilingual name for the unit.
             *
             * @var array{en: string, ar: string}
             *
             * @example {"en": "Kilogram", "ar": "كيلوجرام"}
             */
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            /**
             * Numeric multiplier value for the unit (default 1).
             *
             * @var float|null
             *
             * @example 1
             */
            'value' => 'nullable|numeric|min:0',
        ]);

        $unit = Unit::create([
            'name' => $validated['name'],
            'value' => $validated['value'] ?? 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Unit created successfully.',
            'data' => new UnitResource($unit),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified unit.
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new UnitResource($unit),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified unit.
     */
    public function update(Request $request, Unit $unit): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            /**
             * Multilingual name for the unit.
             *
             * @var array{en: string, ar: string}|null
             *
             * @example {"en": "Kilogram", "ar": "كيلوجرام"}
             */
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            /**
             * Numeric multiplier value for the unit (default 1).
             *
             * @var float|null
             *
             * @example 1
             */
            'value' => 'sometimes|nullable|numeric|min:0',
        ]);

        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }

        if (array_key_exists('value', $validated)) {
            $dataToUpdate['value'] = $validated['value'] ?? 1;
        }

        if (! empty($dataToUpdate)) {
            $unit->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Unit updated successfully.',
            'data' => new UnitResource($unit->fresh()),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return response()->json([
            'status' => true,
            'message' => 'Unit deleted successfully.',
        ]);
    }

    /**
     * Shared select options for units.
     *
     * @return array<string, mixed>
     */
    private function getSelectOptions(): array
    {
        return [
            'units' => Unit::select('id', 'name', 'value')->get(),
        ];
    }

    /**
     * Normalize name field if passed as simple string or JSON string.
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
