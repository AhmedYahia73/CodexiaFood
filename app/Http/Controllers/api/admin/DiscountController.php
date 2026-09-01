<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $discounts = Discount::latest()->paginate($request->get('per_page', 15));

        return DiscountResource::collection($discounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,value',
            'amount' => 'required|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $discount = Discount::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Discount created successfully.',
            'data' => new DiscountResource($discount),
        ], 201);
    }

    public function show(Discount $discount): DiscountResource
    {
        return new DiscountResource($discount);
    }

    public function update(Request $request, Discount $discount): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:percentage,value',
            'amount' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $discount->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Discount updated successfully.',
            'data' => new DiscountResource($discount->fresh()),
        ]);
    }

    public function destroy(Discount $discount): JsonResponse
    {
        $discount->delete();

        return response()->json([
            'status' => true,
            'message' => 'Discount deleted successfully.',
        ]);
    }
}
