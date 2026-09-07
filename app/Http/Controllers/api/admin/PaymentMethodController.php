<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    use image;

    /**
     * Get select options for payment method forms.
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
        $paymentMethods = PaymentMethod::latest()->paginate($request->get('per_page', 15));

        return PaymentMethodResource::collection($paymentMethods)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeMultilingualInputs($request);

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'icon' => $request->hasFile('icon')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'nullable|string|max:255',
            'status' => 'nullable|boolean',
        ]);

        $iconPath = $request->hasFile('icon')
            ? $this->upload($request, 'icon', 'payment_methods')
            : $request->input('icon');

        $paymentMethod = PaymentMethod::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $iconPath,
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Payment method created successfully.',
            'data' => new PaymentMethodResource($paymentMethod),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new PaymentMethodResource($paymentMethod),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->normalizeMultilingualInputs($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'icon' => $request->hasFile('icon')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'nullable|string|max:255',
            'status' => 'nullable|boolean',
        ]);

        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }
        if ($request->has('description')) {
            $dataToUpdate['description'] = $validated['description'] ?? null;
        }
        if ($request->has('status')) {
            $dataToUpdate['status'] = $validated['status'] ?? true;
        }

        if ($request->hasFile('icon')) {
            $newIconPath = $this->update_image($request, $paymentMethod->icon, 'icon', 'payment_methods');
            if ($newIconPath) {
                $dataToUpdate['icon'] = $newIconPath;
            }
        } elseif ($request->filled('icon') && is_string($request->input('icon'))) {
            $dataToUpdate['icon'] = $request->input('icon');
        }

        if (! empty($dataToUpdate)) {
            $paymentMethod->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment method updated successfully.',
            'data' => new PaymentMethodResource($paymentMethod->fresh()),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        if ($paymentMethod->icon) {
            $this->deleteImage($paymentMethod->icon);
        }

        $paymentMethod->delete();

        return response()->json([
            'status' => true,
            'message' => 'Payment method deleted successfully.',
        ]);
    }

    /**
     * Shared select options for payment methods.
     */
    private function getSelectOptions(): array
    {
        return [
            'payment_methods' => PaymentMethod::select('id', 'name', 'icon', 'status')->get(),
        ];
    }

    /**
     * Normalize multilingual inputs for name and description.
     */
    private function normalizeMultilingualInputs(Request $request): void
    {
        foreach (['name', 'description'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                } else {
                    $request->merge([
                        $field => [
                            'en' => $request->input($field),
                            'ar' => $request->input($field),
                        ],
                    ]);
                }
            }
        }
    }
}
