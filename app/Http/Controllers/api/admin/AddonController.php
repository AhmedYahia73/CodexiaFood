<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddonResource;
use App\Models\Addon;
use App\Models\Discount;
use App\Models\Kitchen;
use App\Models\Tax;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddonController extends Controller
{
    use image;

    /**
     * Single API endpoint for addon lists (taxes, discounts, kitchens).
     */
    public function lists(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Alias for lists endpoint.
     */
    public function selectOptions(): JsonResponse
    {
        return $this->lists();
    }

    /**
     * Display a listing of addons with pagination and select options.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
            'kitchen_id' => 'sometimes|nullable|integer|exists:kitchens,id',
            'search' => 'sometimes|nullable|string|max:255',
        ]);

        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $query = Addon::with(['tax', 'discount', 'kitchen'])->latest();

        if (! empty($validated['kitchen_id'])) {
            $query->where('kitchen_id', $validated['kitchen_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%");
            });
        }

        $addons = $query->paginate($perPage);

        return AddonResource::collection($addons)->additional([
            'status' => true,
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created addon in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'image' => $request->hasFile('image')
                ? 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096'
                : 'required|string|max:255',
            'tax_id' => 'nullable|exists:taxes,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'kitchen_id' => 'nullable|exists:kitchens,id',
        ]);

        $imagePath = $request->hasFile('image')
            ? $this->upload($request, 'image', 'addons')
            : $request->input('image');

        $addon = Addon::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'image' => $imagePath,
            'tax_id' => $validated['tax_id'] ?? null,
            'discount_id' => $validated['discount_id'] ?? null,
            'kitchen_id' => $validated['kitchen_id'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Addon created successfully.',
            'data' => new AddonResource($addon->load(['tax', 'discount', 'kitchen'])),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified addon.
     */
    public function show(Addon $addon): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new AddonResource($addon->load(['tax', 'discount', 'kitchen'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified addon in storage.
     */
    public function update(Request $request, Addon $addon): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'image' => $request->hasFile('image')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096'
                : 'nullable|string|max:255',
            'tax_id' => 'nullable|exists:taxes,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'kitchen_id' => 'nullable|exists:kitchens,id',
        ]);

        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }
        if (isset($validated['price'])) {
            $dataToUpdate['price'] = $validated['price'];
        }
        if ($request->has('tax_id')) {
            $dataToUpdate['tax_id'] = $validated['tax_id'] ?? null;
        }
        if ($request->has('discount_id')) {
            $dataToUpdate['discount_id'] = $validated['discount_id'] ?? null;
        }
        if ($request->has('kitchen_id')) {
            $dataToUpdate['kitchen_id'] = $validated['kitchen_id'] ?? null;
        }

        if ($request->hasFile('image')) {
            $newImagePath = $this->update_image($request, $addon->image, 'image', 'addons');
            if ($newImagePath) {
                $dataToUpdate['image'] = $newImagePath;
            }
        } elseif ($request->filled('image') && is_string($request->input('image'))) {
            if ($addon->image && $addon->image !== $request->input('image')) {
                $this->deleteImage($addon->image);
            }
            $dataToUpdate['image'] = $request->input('image');
        }

        if (! empty($dataToUpdate)) {
            $addon->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Addon updated successfully.',
            'data' => new AddonResource($addon->fresh(['tax', 'discount', 'kitchen'])),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified addon from storage.
     */
    public function destroy(Addon $addon): JsonResponse
    {
        if ($addon->image) {
            $this->deleteImage($addon->image);
        }

        $addon->delete();

        return response()->json([
            'status' => true,
            'message' => 'Addon deleted successfully.',
        ]);
    }

    /**
     * Shared select options for addons.
     */
    private function getSelectOptions(): array
    {
        return [
            'taxes' => $this->formatTaxes(),
            'discounts' => $this->formatDiscounts(),
            'kitchens' => Kitchen::select('id', 'name', 'branch_id')->get(),
        ];
    }

    /**
     * Format taxes list with id, name en, and name ar.
     */
    private function formatTaxes(): array
    {
        return Tax::select('id', 'name', 'type', 'amount', 'status')
            ->get()
            ->map(function (Tax $tax) {
                $nameEn = is_array($tax->name) ? ($tax->name['en'] ?? '') : (string) $tax->name;
                $nameAr = is_array($tax->name) ? ($tax->name['ar'] ?? '') : (string) $tax->name;

                return [
                    'id' => $tax->id,
                    'name' => [
                        'en' => $nameEn,
                        'ar' => $nameAr,
                    ],
                    'name_en' => $nameEn,
                    'name_ar' => $nameAr,
                    'type' => $tax->type,
                    'amount' => (float) $tax->amount,
                    'status' => (bool) $tax->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Format discounts list with id, name en, and name ar.
     */
    private function formatDiscounts(): array
    {
        return Discount::select('id', 'name', 'type', 'amount', 'status')
            ->get()
            ->map(function (Discount $discount) {
                $nameEn = is_array($discount->name) ? ($discount->name['en'] ?? '') : (string) $discount->name;
                $nameAr = is_array($discount->name) ? ($discount->name['ar'] ?? '') : (string) $discount->name;

                return [
                    'id' => $discount->id,
                    'name' => [
                        'en' => $nameEn,
                        'ar' => $nameAr,
                    ],
                    'name_en' => $nameEn,
                    'name_ar' => $nameAr,
                    'type' => $discount->type,
                    'amount' => (float) $discount->amount,
                    'status' => (bool) $discount->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Normalize multilingual name input.
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
