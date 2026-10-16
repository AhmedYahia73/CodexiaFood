<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalerResource;
use App\Models\Saler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalerController extends Controller
{
    /**
     * Get select options for saler forms.
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
        $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:-1',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $query = Saler::latest();

        $salers = $perPage === -1 ? $query->paginate(1000) : $query->paginate($perPage);

        return SalerResource::collection($salers)->additional([
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

        $saler = Saler::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'balance' => $validated['balance'] ?? 0.00,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Saler created successfully.',
            'data' => new SalerResource($saler),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Saler $saler): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new SalerResource($saler),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Note: Balance cannot be updated via update endpoint.
     */
    public function update(Request $request, Saler $saler): JsonResponse
    {
        $this->normalizeNameInput($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

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
            $saler->update($dataToUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Saler updated successfully.',
            'data' => new SalerResource($saler->fresh()),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Saler $saler): JsonResponse
    {
        $saler->delete();

        return response()->json([
            'status' => true,
            'message' => 'Saler deleted successfully.',
        ]);
    }

    /**
     * Shared select options for salers.
     */
    private function getSelectOptions(): array
    {
        return [
            'salers' => Saler::select('id', 'name', 'phone', 'balance')->get(),
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
