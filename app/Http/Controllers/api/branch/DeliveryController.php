<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\DeliveryResource;
use App\Models\Branch;
use App\Models\Delivery;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends BaseBranchController
{
    use image;

    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
                'deliveries' => Delivery::where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                })->select('id', 'name')->get(),
            ],
        ]);
    }

    /**
     * Return deliveries id and name strictly for select dropdowns.
     */
    public function select(): JsonResponse
    {
        $branchId = $this->getBranchId();

        $deliveries = Delivery::where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        })->select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $deliveries,
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $deliveries = Delivery::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return DeliveryResource::collection($deliveries)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'id_images' => 'nullable',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $uploadedPaths = [];
        if ($request->hasFile('id_images')) {
            $files = $request->file('id_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $uploadedPaths[] = $file->store('deliveries', 'public');
                }
            }
        }

        $delivery = Delivery::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'id_images' => $uploadedPaths,
            'branch_id' => $branchId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Delivery created successfully.',
            'data' => new DeliveryResource($delivery->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Delivery $delivery): JsonResponse
    {
        $this->ensureBelongsToBranch($delivery);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new DeliveryResource($delivery->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Delivery $delivery): JsonResponse
    {
        $this->ensureBelongsToBranch($delivery);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:255',
            'id_images' => 'nullable',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'sometimes|nullable|integer',
            'existing_images' => 'nullable',
            'deleted_images' => 'nullable',
        ]);

        $currentImages = is_array($delivery->id_images) ? $delivery->id_images : [];

        // Remove deleted images
        if ($request->filled('deleted_images')) {
            $deleted = $request->input('deleted_images');
            $deleted = is_array($deleted) ? $deleted : json_decode($deleted, true) ?? [$deleted];
            foreach ($deleted as $delImg) {
                $this->deleteImage($delImg);
                $currentImages = array_values(array_filter($currentImages, fn ($img) => $img !== $delImg));
            }
        }

        // Upload new images
        if ($request->hasFile('id_images')) {
            $files = $request->file('id_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $currentImages[] = $file->store('deliveries', 'public');
                }
            }
        }

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ], fn ($val) => ! is_null($val));

        $dataToUpdate['id_images'] = $currentImages;
        $dataToUpdate['branch_id'] = $branchId;

        $delivery->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Delivery updated successfully.',
            'data' => new DeliveryResource($delivery->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        $this->ensureBelongsToBranch($delivery);

        if (is_array($delivery->id_images)) {
            foreach ($delivery->id_images as $img) {
                $this->deleteImage($img);
            }
        }

        $delivery->delete();

        return response()->json([
            'status' => true,
            'message' => 'Delivery deleted successfully.',
        ]);
    }
}
