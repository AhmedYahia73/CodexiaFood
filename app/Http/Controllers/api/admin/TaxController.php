<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxResource;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxController extends Controller
{
    /**
     * Get select options for tax forms.
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
        $taxes = Tax::latest()->paginate($request->get('per_page', 15));

        return TaxResource::collection($taxes)->additional([
            'select_options' => $this->getSelectOptions(),
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
            'type' => 'required|in:percentage,value',
            'amount' => 'required|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $tax = Tax::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tax created successfully.',
            'data' => new TaxResource($tax),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Tax $tax): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new TaxResource($tax),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tax $tax): JsonResponse
    {
        $this->normalizeMultilingualName($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'type' => 'sometimes|required|in:percentage,value',
            'amount' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }
        if (isset($validated['type'])) {
            $dataToUpdate['type'] = $validated['type'];
        }
        if (isset($validated['amount'])) {
            $dataToUpdate['amount'] = $validated['amount'];
        }
        if ($request->has('status')) {
            $dataToUpdate['status'] = $validated['status'] ?? true;
        }

        if (! empty($dataToUpdate)) {
            $tax->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tax updated successfully.',
            'data' => new TaxResource($tax->fresh()),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tax $tax): JsonResponse
    {
        $tax->delete();

        return response()->json([
            'status' => true,
            'message' => 'Tax deleted successfully.',
        ]);
    }

    /**
     * Shared select options for taxes.
     */
    private function getSelectOptions(): array
    {
        return [
            'taxes' => Tax::select('id', 'name', 'type', 'amount', 'status')->get(),
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
