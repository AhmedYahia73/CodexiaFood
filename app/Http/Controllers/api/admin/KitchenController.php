<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\KitchenResource;
use App\Models\Branch;
use App\Models\Kitchen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $kitchens = Kitchen::with('branch')->latest()->paginate($request->get('per_page', 15));

        return KitchenResource::collection($kitchens)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge([
                'name' => [
                    'en' => $request->input('name'),
                    'ar' => $request->input('name'),
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'user_name' => 'nullable|string|max:255|unique:kitchens,user_name',
            'password' => 'required|string|min:6',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $kitchen = Kitchen::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Kitchen created successfully.',
            'data' => new KitchenResource($kitchen->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Kitchen $kitchen): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new KitchenResource($kitchen->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Kitchen $kitchen): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge([
                'name' => [
                    'en' => $request->input('name'),
                    'ar' => $request->input('name'),
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'user_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('kitchens', 'user_name')->ignore($kitchen->id),
            ],
            'password' => 'nullable|string|min:6',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $kitchen->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Kitchen updated successfully.',
            'data' => new KitchenResource($kitchen->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Kitchen $kitchen): JsonResponse
    {
        $kitchen->delete();

        return response()->json([
            'status' => true,
            'message' => 'Kitchen deleted successfully.',
        ]);
    }
}
