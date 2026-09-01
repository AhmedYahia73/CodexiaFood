<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = Branch::latest()->paginate($request->get('per_page', 15));

        return BranchResource::collection($branches);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'watts' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $branch = Branch::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Branch created successfully.',
            'data' => new BranchResource($branch),
        ], 201);
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
            'watts' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $branch->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Branch updated successfully.',
            'data' => new BranchResource($branch->fresh()),
        ]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();

        return response()->json([
            'status' => true,
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
