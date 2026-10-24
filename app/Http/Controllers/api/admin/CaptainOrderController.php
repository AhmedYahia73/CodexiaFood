<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CaptainOrderResource;
use App\Models\CaptainOrder;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @tags Admin Captain Orders
 */
class CaptainOrderController extends Controller
{
    use image;

    /**
     * Return select options for dropdowns.
     */
    public function selectOptions(): JsonResponse
    {
        $captains = CaptainOrder::where('status', true)
            ->select('id', 'name', 'user_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'captain_orders' => $captains,
            ],
        ]);
    }

    /**
     * Display a listing of captain orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Page number for pagination.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',
            /**
             * Number of items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',
            /**
             * Search term for name or username.
             *
             * @var string|null
             *
             * @example "John"
             */
            'search' => 'sometimes|nullable|string|max:255',
            /**
             * Filter by status (1 for active, 0 for inactive).
             *
             * @var bool|null
             *
             * @example true
             */
            'status' => 'sometimes|nullable|boolean',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $captainOrders = CaptainOrder::query()
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('user_name', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('status', $validated) && ! is_null($validated['status']), function ($query) use ($validated) {
                $query->where('status', (bool) $validated['status']);
            })
            ->latest('id')
            ->paginate($perPage);

        return CaptainOrderResource::collection($captainOrders);
    }

    /**
     * Store a newly created captain order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Captain full name.
             *
             * @var string
             *
             * @example "Ahmed Mohamed"
             */
            'name' => 'required|string|max:255',
            /**
             * Unique username for login.
             *
             * @var string
             *
             * @example "ahmed_captain"
             */
            'user_name' => 'required|string|max:255|unique:captain_orders,user_name',
            /**
             * Login password.
             *
             * @var string
             *
             * @example "secret123"
             */
            'password' => 'required|string|min:6',
            /**
             * Status of the captain (active = true, inactive = false).
             *
             * @var bool|null
             *
             * @example true
             */
            'status' => 'sometimes|nullable|boolean',
            /**
             * Uploaded images for the captain order.
             *
             * @var array|null
             */
            'images' => 'sometimes|nullable',
            /**
             * Image file items.
             *
             * @var string|null
             */
            'images.*' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $uploadedPaths = [];
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $uploadedPaths[] = $file->store('captain_orders', 'public');
                }
            }
        }

        $captainOrder = CaptainOrder::create([
            'name' => $validated['name'],
            'user_name' => $validated['user_name'],
            'password' => $validated['password'],
            'status' => array_key_exists('status', $validated) ? (bool) $validated['status'] : true,
            'images' => $uploadedPaths,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Captain order created successfully.',
            'data' => new CaptainOrderResource($captainOrder),
        ], 201);
    }

    /**
     * Display the specified captain order.
     */
    public function show(CaptainOrder $captainOrder): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new CaptainOrderResource($captainOrder),
        ]);
    }

    /**
     * Update the specified captain order.
     */
    public function update(Request $request, CaptainOrder $captainOrder): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Captain full name.
             *
             * @var string|null
             *
             * @example "Ahmed Mohamed Updated"
             */
            'name' => 'sometimes|required|string|max:255',
            /**
             * Unique username for login.
             *
             * @var string|null
             *
             * @example "ahmed_captain_updated"
             */
            'user_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('captain_orders', 'user_name')->ignore($captainOrder->id),
            ],
            /**
             * New login password (leave empty to keep unchanged).
             *
             * @var string|null
             *
             * @example "newSecret123"
             */
            'password' => 'sometimes|nullable|string|min:6',
            /**
             * Status of the captain (active = true, inactive = false).
             *
             * @var bool|null
             *
             * @example true
             */
            'status' => 'sometimes|nullable|boolean',
            /**
             * Newly uploaded image files to append.
             *
             * @var array|null
             */
            'new_images' => 'sometimes|nullable',
            /**
             * New image file item.
             *
             * @var string|null
             */
            'new_images.*' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            /**
             * Fallback uploaded image files to append.
             *
             * @var array|null
             */
            'images' => 'sometimes|nullable',
            /**
             * Fallback image file item.
             *
             * @var string|null
             */
            'images.*' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            /**
             * List of image paths/URLs to delete from storage before updating.
             *
             * @var array|string|null
             */
            'deleted_images' => 'sometimes|nullable',
        ]);

        $dataToUpdate = [];

        if (array_key_exists('name', $validated)) {
            $dataToUpdate['name'] = $validated['name'];
        }

        if (array_key_exists('user_name', $validated)) {
            $dataToUpdate['user_name'] = $validated['user_name'];
        }

        if (! empty($validated['password'])) {
            $dataToUpdate['password'] = $validated['password'];
        }

        if (array_key_exists('status', $validated) && ! is_null($validated['status'])) {
            $dataToUpdate['status'] = (bool) $validated['status'];
        }

        $currentImages = is_array($captainOrder->images)
            ? $captainOrder->images
            : (json_decode($captainOrder->images ?? '[]', true) ?: []);

        $imagesChanged = false;

        // 1. Process deleted_images: delete files from server storage first, then remove from array
        if ($request->has('deleted_images')) {
            $deletedInput = $request->input('deleted_images');
            if (is_string($deletedInput)) {
                $decoded = json_decode($deletedInput, true);
                $deletedInput = is_array($decoded) ? $decoded : [$deletedInput];
            }
            $deletedInput = is_array($deletedInput) ? $deletedInput : [$deletedInput];

            $deletedNormalized = array_values(array_filter(array_map([$this, 'normalizeStoragePath'], $deletedInput)));
            $deletedBasenames = array_map('basename', $deletedNormalized);

            $remainingImages = [];
            foreach ($currentImages as $image) {
                $norm = $this->normalizeStoragePath($image);
                $base = basename($image);

                if (in_array($norm, $deletedNormalized, true) || in_array($base, $deletedBasenames, true)) {
                    $this->deleteStoredFile($image);
                    $imagesChanged = true;
                } else {
                    $remainingImages[] = $image;
                }
            }

            $currentImages = $remainingImages;
        }

        // 2. Process new_images: upload new files to server and append to array
        if ($request->hasFile('new_images')) {
            $newFiles = $request->file('new_images');
            $newFiles = is_array($newFiles) ? $newFiles : [$newFiles];
            foreach ($newFiles as $file) {
                if ($file && $file->isValid()) {
                    $currentImages[] = $file->store('captain_orders', 'public');
                    $imagesChanged = true;
                }
            }
        }

        // 2b. Also accept 'images' files if provided as new images
        if ($request->hasFile('images')) {
            $extraFiles = $request->file('images');
            $extraFiles = is_array($extraFiles) ? $extraFiles : [$extraFiles];
            foreach ($extraFiles as $file) {
                if ($file && $file->isValid()) {
                    $currentImages[] = $file->store('captain_orders', 'public');
                    $imagesChanged = true;
                }
            }
        }

        if ($imagesChanged) {
            $dataToUpdate['images'] = array_values(array_unique($currentImages));
        }

        $captainOrder->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Captain order updated successfully.',
            'data' => new CaptainOrderResource($captainOrder->fresh()),
        ]);
    }

    /**
     * Remove the specified captain order from storage.
     */
    public function destroy(CaptainOrder $captainOrder): JsonResponse
    {
        // Delete all stored images from server first
        $images = is_array($captainOrder->images)
            ? $captainOrder->images
            : (json_decode($captainOrder->images ?? '[]', true) ?: []);

        if (! empty($images)) {
            foreach ($images as $image) {
                $this->deleteStoredFile($image);
            }
        }

        $captainOrder->delete();

        return response()->json([
            'status' => true,
            'message' => 'Captain order deleted successfully.',
        ]);
    }

    /**
     * Normalize storage path by stripping host, port, and storage prefixes.
     */
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

    /**
     * Safely delete a file from public storage disk.
     */
    protected function deleteStoredFile(?string $urlOrPath): void
    {
        if (! $urlOrPath) {
            return;
        }

        $relativePath = $this->normalizeStoragePath($urlOrPath);

        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
