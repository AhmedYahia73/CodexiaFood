<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Branch;
use App\Models\Delivery;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends Controller
{
    use image;

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
        $deliveries = Delivery::with('branch')->latest()->paginate($request->get('per_page', 15));

        return DeliveryResource::collection($deliveries)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'id_images' => 'nullable|array',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $uploadedPaths = [];
        if ($request->hasFile('id_images')) {
            $uploadedPaths = $this->upload_array_of_file($request, 'id_images', 'deliveries') ?? [];
        }

        $delivery = Delivery::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'id_images' => $uploadedPaths,
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Delivery created successfully.',
            'data' => new DeliveryResource($delivery->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Delivery $delivery): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new DeliveryResource($delivery->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Delivery $delivery): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:255',
            'id_images' => 'nullable|array',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'branch_id' => $request->has('branch_id') ? $validated['branch_id'] : null,
        ], fn ($val) => ! is_null($val));

        if ($request->hasFile('id_images')) {
            if (! empty($delivery->id_images) && is_array($delivery->id_images)) {
                foreach ($delivery->id_images as $oldImagePath) {
                    $this->deleteImage($oldImagePath);
                }
            }

            $newPaths = $this->upload_array_of_file($request, 'id_images', 'deliveries') ?? [];
            $dataToUpdate['id_images'] = $newPaths;
        }

        $delivery->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Delivery updated successfully.',
            'data' => new DeliveryResource($delivery->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        if (! empty($delivery->id_images) && is_array($delivery->id_images)) {
            foreach ($delivery->id_images as $oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
        }

        $delivery->delete();

        return response()->json([
            'status' => true,
            'message' => 'Delivery deleted successfully.',
        ]);
    }
}
