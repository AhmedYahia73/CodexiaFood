<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    use image;

    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'categories' => Category::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::with('parent')->latest()->paginate($request->get('per_page', 15));

        return CategoryResource::collection($categories)->additional([
            'select_options' => [
                'categories' => Category::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'nullable|in:recipe,material,product',
            'status' => 'nullable|boolean',
        ]);

        $imagePath = $this->upload($request, 'image', 'categories');

        $category = Category::create([
            'name' => $validated['name'],
            'image' => $imagePath,
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'type' => $validated['type'] ?? 'product',
            'status' => $validated['status'] ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category->load('parent')),
            'select_options' => [
                'categories' => Category::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new CategoryResource($category->load('parent')),
            'select_options' => [
                'categories' => Category::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'nullable|in:recipe,material,product',
            'status' => 'nullable|boolean',
        ]);

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'description' => $request->has('description') ? $validated['description'] : null,
            'category_id' => $request->has('category_id') ? $validated['category_id'] : null,
            'type' => $validated['type'] ?? null,
            'status' => $request->has('status') ? $validated['status'] : null,
        ], fn ($val) => ! is_null($val));

        if ($request->hasFile('image')) {
            $newImagePath = $this->update_image($request, $category->image, 'image', 'categories');
            if ($newImagePath) {
                $dataToUpdate['image'] = $newImagePath;
            }
        }

        $category->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category->fresh(['parent'])),
            'select_options' => [
                'categories' => Category::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->image) {
            $this->deleteImage($category->image);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
