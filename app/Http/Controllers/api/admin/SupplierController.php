<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    /**
     * Get select options for supplier forms.
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
        $suppliers = Supplier::latest()->paginate($request->get('per_page', 15));

        return SupplierResource::collection($suppliers)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeNameInput($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'balance' => 'nullable|numeric',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'balance' => $validated['balance'] ?? 0.00,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Supplier created successfully.',
            'data' => new SupplierResource($supplier),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new SupplierResource($supplier),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Note: Balance cannot be updated via update endpoint.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $this->normalizeNameInput($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        // Strictly exclude balance from update
        $dataToUpdate = [];

        if (isset($validated['name'])) {
            $dataToUpdate['name'] = $validated['name'];
        }
        if ($request->has('phone')) {
            $dataToUpdate['phone'] = $validated['phone'] ?? null;
        }
        if ($request->has('email')) {
            $dataToUpdate['email'] = $validated['email'] ?? null;
        }

        if (! empty($dataToUpdate)) {
            $supplier->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Supplier updated successfully.',
            'data' => new SupplierResource($supplier->fresh()),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'status' => true,
            'message' => 'Supplier deleted successfully.',
        ]);
    }

    /**
     * Shared select options for suppliers.
     */
    private function getSelectOptions(): array
    {
        return [
            'suppliers' => Supplier::select('id', 'name', 'phone', 'balance')->get(),
        ];
    }

    /**
     * Normalize name input if array is provided.
     */
    private function normalizeNameInput(Request $request): void
    {
        if ($request->has('name') && is_array($request->input('name'))) {
            $nameArray = $request->input('name');
            $nameStr = $nameArray['ar'] ?? $nameArray['en'] ?? reset($nameArray);
            $request->merge(['name' => (string) $nameStr]);
        }
    }
}
