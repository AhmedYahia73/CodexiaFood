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
            'id_images' => 'nullable',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'nullable|exists:branches,id',
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
            'id_images' => 'nullable',
            'id_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'branch_id' => 'nullable|exists:branches,id',
            'existing_images' => 'nullable',
            'deleted_images' => 'nullable',
        ]);

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'branch_id' => $request->has('branch_id') ? $validated['branch_id'] : null,
        ], fn ($val) => ! is_null($val));

        $currentImages = is_array($delivery->id_images)
            ? $delivery->id_images
            : (json_decode($delivery->id_images, true) ?? []);

        $imagesChanged = false;

        // 1. Handle deleted_images
        if ($request->has('deleted_images')) {
            $deletedInput = $request->input('deleted_images');
            if (is_string($deletedInput)) {
                $decoded = json_decode($deletedInput, true);
                $deletedInput = is_array($decoded) ? $decoded : [$deletedInput];
            }
            $deletedInput = is_array($deletedInput) ? $deletedInput : [$deletedInput];

            $deletedNormalized = array_map([$this, 'normalizeStoragePath'], $deletedInput);

            $remainingImages = [];
            foreach ($currentImages as $image) {
                $norm = $this->normalizeStoragePath($image);
                $shouldDelete = false;
                foreach ($deletedNormalized as $del) {
                    if ($norm === $del || basename($image) === basename($del)) {
                        $shouldDelete = true;
                        break;
                    }
                }

                if ($shouldDelete) {
                    $this->deleteImage($image);
                    $imagesChanged = true;
                } else {
                    $remainingImages[] = $image;
                }
            }
            $currentImages = $remainingImages;
        }

        // 2. Handle existing_images (keep only these from currentImages)
        if ($request->has('existing_images')) {
            $existingInput = $request->input('existing_images');
            if (is_string($existingInput)) {
                $decoded = json_decode($existingInput, true);
                $existingInput = is_array($decoded) ? $decoded : [$existingInput];
            }
            $existingInput = is_array($existingInput) ? $existingInput : [$existingInput];

            $existingNormalized = array_map([$this, 'normalizeStoragePath'], $existingInput);

            $keptImages = [];
            foreach ($currentImages as $image) {
                $norm = $this->normalizeStoragePath($image);
                $shouldKeep = false;
                foreach ($existingNormalized as $exist) {
                    if ($norm === $exist || basename($image) === basename($exist)) {
                        $shouldKeep = true;
                        break;
                    }
                }

                if ($shouldKeep) {
                    $keptImages[] = $image;
                } else {
                    $this->deleteImage($image);
                    $imagesChanged = true;
                }
            }
            $currentImages = $keptImages;
        }

        // 3. Handle new uploaded id_images
        if ($request->hasFile('id_images')) {
            $files = $request->file('id_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $newPath = $file->store('deliveries', 'public');
                    $currentImages[] = $newPath;
                    $imagesChanged = true;
                }
            }
        }

        if ($imagesChanged) {
            $dataToUpdate['id_images'] = array_values(array_unique($currentImages));
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

    protected function normalizeStoragePath(?string $urlOrPath): string
    {
        if (! $urlOrPath) {
            return '';
        }

        $trimmed = trim($urlOrPath);
        $path = parse_url($trimmed, PHP_URL_PATH) ?? $trimmed;

        if (str_contains($path, 'storage/')) {
            return ltrim(substr($path, strpos($path, 'storage/') + strlen('storage/')), '/');
        }

        return ltrim($path, '/');
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
