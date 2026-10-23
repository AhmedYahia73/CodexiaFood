<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * @tags Admin Banners
 */
class BannerController extends Controller
{
    use image;

    /**
     * Display a listing of banners.
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
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $banners = Banner::latest('id')->paginate($perPage);

        return BannerResource::collection($banners);
    }

    /**
     * Store a newly created banner.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Banner image file.
             *
             * @var string
             */
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $imagePath = $this->upload($request, 'image', 'banners');

        $banner = Banner::create([
            'image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Banner created successfully.',
            'data' => new BannerResource($banner),
        ], 201);
    }

    /**
     * Display the specified banner.
     */
    public function show(Banner $banner): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new BannerResource($banner),
        ]);
    }

    /**
     * Remove the specified banner from storage.
     */
    public function destroy(Banner $banner): JsonResponse
    {
        if ($banner->image) {
            $relativePath = str_replace('storage/', '', ltrim($banner->image, '/'));
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
            $this->deleteImage($banner->image);
        }

        $banner->delete();

        return response()->json([
            'status' => true,
            'message' => 'Banner deleted successfully.',
        ]);
    }
}
