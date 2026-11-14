<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Option;
use App\Models\Product;
use App\Services\ProductManufacturingService;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use image;

    /**
     * Get select options for product forms.
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
        $validated = $request->validate([
            /**
             * Current page number.
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

            /**
             * Filter by specific product ID. When filtered by ID, manufacturing recipes are included.
             *
             * @var int|null
             *
             * @example 1
             */
            'id' => 'sometimes|nullable|integer|exists:products,id',

            /**
             * Whether to include manufacturing recipes in product list.
             *
             * @var bool|null
             *
             * @example false
             */
            'include_recipes' => 'sometimes|nullable|boolean',
        ]);

        $includeRecipes = ! empty($validated['id']) || $request->boolean('include_recipes');

        $query = Product::with(ProductManufacturingService::eagerLoadRelations($includeRecipes));

        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }

        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));
        $products = $query->latest()->paginate($perPage);

        return ProductResource::collection($products)->additional([
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeProductRequest($request);

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => $request->hasFile('image')
                ? 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096'
                : 'required|string|max:255',
            'tax_id' => 'nullable|exists:taxes,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'kitchen_id' => 'nullable|exists:kitchens,id',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required|array:en,ar',
            'variations.*.name.en' => 'required|string|max:255',
            'variations.*.name.ar' => 'required|string|max:255',
            'variations.*.status' => 'nullable|boolean',
            'variations.*.required' => 'nullable|boolean',
            'variations.*.options' => 'nullable|array',
            'variations.*.options.*.name' => 'required|array:en,ar',
            'variations.*.options.*.name.en' => 'required|string|max:255',
            'variations.*.options.*.name.ar' => 'required|string|max:255',
            'variations.*.options.*.price' => 'nullable|numeric|min:0',
            'variations.*.options.*.status' => 'nullable|boolean',

            /**
             * Manufacturing recipes / ingredients (product-level, for products without options).
             *
             * @var array|null
             */
            'recipes' => 'nullable|array',
            'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
            'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
            'recipes.*.count' => 'required_with:recipes|integer|min:1',
            'recipes.*.unit_id' => 'required_with:recipes|integer|exists:units,id',

            /**
             * Manufacturing recipes per option combination (for products with variations).
             *
             * @var array|null
             */
            'option_recipes' => 'nullable|array',
            'option_recipes.*.option_ids' => 'required|array|min:1',
            'option_recipes.*.option_ids.*' => 'integer',
            'option_recipes.*.recipes' => 'required|array|min:1',
            'option_recipes.*.recipes.*.material_id' => 'nullable|exists:materials,id|required_without:option_recipes.*.recipes.*.product_recipe_id',
            'option_recipes.*.recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:option_recipes.*.recipes.*.material_id',
            'option_recipes.*.recipes.*.count' => 'required|integer|min:1',
            'option_recipes.*.recipes.*.unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $imagePath = $request->hasFile('image')
            ? $this->upload($request, 'image', 'products')
            : $request->input('image');

        $product = DB::transaction(function () use ($validated, $imagePath, $request) {
            $product = Product::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'image' => $imagePath,
                'tax_id' => $validated['tax_id'] ?? null,
                'discount_id' => $validated['discount_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'kitchen_id' => $validated['kitchen_id'] ?? null,
            ]);

            if (! empty($validated['variations'])) {
                foreach ($validated['variations'] as $varData) {
                    $variation = $product->variations()->create([
                        'name' => $varData['name'],
                        'status' => $varData['status'] ?? true,
                        'required' => $varData['required'] ?? false,
                    ]);

                    if (! empty($varData['options'])) {
                        foreach ($varData['options'] as $optData) {
                            $variation->options()->create([
                                'product_id' => $product->id,
                                'name' => $optData['name'],
                                'price' => $optData['price'] ?? 0,
                                'status' => $optData['status'] ?? true,
                            ]);
                        }
                    }
                }
            }

            if ($request->has('option_recipes') && ! empty($validated['option_recipes'])) {
                $this->validateAllCombinationsCovered($product, $validated['option_recipes']);
                ProductManufacturingService::syncOptionRecipesForProduct($product, $validated['option_recipes']);
            } elseif ($request->has('recipes')) {
                ProductManufacturingService::syncForProduct($product, $validated['recipes'] ?? []);
            }

            return $product;
        });

        $product->load(ProductManufacturingService::eagerLoadRelations(true));

        return response()->json([
            'status' => true,
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
            'select_options' => $this->getSelectOptions(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(ProductManufacturingService::eagerLoadRelations(true));

        return response()->json([
            'status' => true,
            'data' => new ProductResource($product),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->normalizeProductRequest($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'image' => $request->hasFile('image')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096'
                : 'nullable|string|max:255',
            'tax_id' => 'nullable|exists:taxes,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'kitchen_id' => 'nullable|exists:kitchens,id',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required|array:en,ar',
            'variations.*.name.en' => 'required|string|max:255',
            'variations.*.name.ar' => 'required|string|max:255',
            'variations.*.status' => 'nullable|boolean',
            'variations.*.required' => 'nullable|boolean',
            'variations.*.options' => 'nullable|array',
            'variations.*.options.*.name' => 'required|array:en,ar',
            'variations.*.options.*.name.en' => 'required|string|max:255',
            'variations.*.options.*.name.ar' => 'required|string|max:255',
            'variations.*.options.*.price' => 'nullable|numeric|min:0',
            'variations.*.options.*.status' => 'nullable|boolean',

            /**
             * Manufacturing recipes / ingredients (product-level, for products without options).
             *
             * @var array|null
             */
            'recipes' => 'nullable|array',
            'recipes.*.material_id' => 'nullable|exists:materials,id|required_without:recipes.*.product_recipe_id',
            'recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:recipes.*.material_id',
            'recipes.*.count' => 'required_with:recipes|integer|min:1',
            'recipes.*.unit_id' => 'required_with:recipes|integer|exists:units,id',

            /**
             * Manufacturing recipes per option combination (for products with variations).
             *
             * @var array|null
             */
            'option_recipes' => 'nullable|array',
            'option_recipes.*.option_ids' => 'required|array|min:1',
            'option_recipes.*.option_ids.*' => 'integer',
            'option_recipes.*.recipes' => 'required|array|min:1',
            'option_recipes.*.recipes.*.material_id' => 'nullable|exists:materials,id|required_without:option_recipes.*.recipes.*.product_recipe_id',
            'option_recipes.*.recipes.*.product_recipe_id' => 'nullable|exists:product_recipes,id|required_without:option_recipes.*.recipes.*.material_id',
            'option_recipes.*.recipes.*.count' => 'required|integer|min:1',
            'option_recipes.*.recipes.*.unit_id' => 'nullable|integer|exists:units,id',
        ]);

        DB::transaction(function () use ($request, $validated, $product) {
            $productData = [];

            if (isset($validated['name'])) {
                $productData['name'] = $validated['name'];
            }
            if ($request->has('description')) {
                $productData['description'] = $validated['description'] ?? null;
            }
            if (isset($validated['price'])) {
                $productData['price'] = $validated['price'];
            }
            if ($request->has('tax_id')) {
                $productData['tax_id'] = $validated['tax_id'] ?? null;
            }
            if ($request->has('discount_id')) {
                $productData['discount_id'] = $validated['discount_id'] ?? null;
            }
            if ($request->has('category_id')) {
                $productData['category_id'] = $validated['category_id'] ?? null;
            }
            if ($request->has('sub_category_id')) {
                $productData['sub_category_id'] = $validated['sub_category_id'] ?? null;
            }
            if ($request->has('kitchen_id')) {
                $productData['kitchen_id'] = $validated['kitchen_id'] ?? null;
            }

            if ($request->hasFile('image')) {
                $newImagePath = $this->update_image($request, $product->image, 'image', 'products');
                if ($newImagePath) {
                    $productData['image'] = $newImagePath;
                }
            } elseif ($request->filled('image') && is_string($request->input('image'))) {
                $productData['image'] = $request->input('image');
            }

            if (! empty($productData)) {
                $product->update($productData);
            }

            if ($request->has('variations')) {
                $product->options()->delete();
                $product->variations()->delete();

                if (! empty($validated['variations'])) {
                    foreach ($validated['variations'] as $varData) {
                        $variation = $product->variations()->create([
                            'name' => $varData['name'],
                            'status' => $varData['status'] ?? true,
                            'required' => $varData['required'] ?? false,
                        ]);

                        if (! empty($varData['options'])) {
                            foreach ($varData['options'] as $optData) {
                                $variation->options()->create([
                                    'product_id' => $product->id,
                                    'name' => $optData['name'],
                                    'price' => $optData['price'] ?? 0,
                                    'status' => $optData['status'] ?? true,
                                ]);
                            }
                        }
                    }
                }
            }

            if ($request->has('option_recipes') && ! empty($validated['option_recipes'])) {
                // Refresh variations from DB after potential update above
                $product->load('variations.options');
                $this->validateAllCombinationsCovered($product, $validated['option_recipes']);
                ProductManufacturingService::syncOptionRecipesForProduct($product, $validated['option_recipes']);
            } elseif ($request->has('recipes')) {
                ProductManufacturingService::syncForProduct($product, $validated['recipes'] ?? []);
            }
        });

        $product->load(ProductManufacturingService::eagerLoadRelations(true));

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product),
            'select_options' => $this->getSelectOptions(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->image) {
            $this->deleteImage($product->image);
        }

        ProductManufacturingService::deleteForProduct($product);

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * Shared select options payload.
     */
    private function getSelectOptions(): array
    {
        return ProductManufacturingService::getSelectOptions();
    }

    /**
     * Validate that all possible option combinations are covered.
     *
     * @param  array<int, array{option_ids: array<int, int>, recipes: array}>  $optionRecipes
     *
     * @throws ValidationException
     */
    private function validateAllCombinationsCovered(Product $product, array $optionRecipes): void
    {
        $allCombinations = ProductManufacturingService::getAllCombinations($product);

        if (empty($allCombinations)) {
            return; // Product has no variations with options; nothing to validate.
        }

        $expectedKeys = collect($allCombinations)
            ->map(fn ($combo) => ProductManufacturingService::buildCombinationKey($combo))
            ->sort()
            ->values();

        $providedKeys = collect($optionRecipes)
            ->map(fn ($cr) => ProductManufacturingService::buildCombinationKey($cr['option_ids'] ?? []))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $missingKeys = $expectedKeys->diff($providedKeys);

        if ($missingKeys->isNotEmpty()) {
            $missingDescriptions = $missingKeys->map(function (string $key) {
                $optionIds = explode(',', $key);
                $optionNames = Option::whereIn('id', $optionIds)
                    ->get()
                    ->map(fn ($o) => is_array($o->name) ? ($o->name['ar'] ?? $o->name['en'] ?? $o->id) : $o->name)
                    ->implode(' + ');

                return $optionNames ?: $key;
            })->implode(', ');

            throw ValidationException::withMessages([
                'option_recipes' => "يجب تحديد الوصفة لجميع مجموعات الاختيارات. المجموعات الناقصة: {$missingDescriptions}",
            ]);
        }
    }

    /**
     * Normalize multilingual inputs if string or JSON string provided.
     */
    private function normalizeMultilingualInput(mixed $input): mixed
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [
                'en' => $input,
                'ar' => $input,
            ];
        }

        return $input;
    }

    /**
     * Normalize request attributes for products, variations, and options.
     */
    private function normalizeProductRequest(Request $request): void
    {
        $dataToMerge = [];

        if ($request->has('name')) {
            $dataToMerge['name'] = $this->normalizeMultilingualInput($request->input('name'));
        }

        if ($request->has('description') && ! is_null($request->input('description'))) {
            $dataToMerge['description'] = $this->normalizeMultilingualInput($request->input('description'));
        }

        if ($request->has('variations')) {
            $variations = $request->input('variations');
            if (is_string($variations)) {
                $variations = json_decode($variations, true) ?? [];
            }
            if (is_array($variations)) {
                foreach ($variations as $i => $var) {
                    if (isset($var['name'])) {
                        $variations[$i]['name'] = $this->normalizeMultilingualInput($var['name']);
                    }
                    if (isset($var['options'])) {
                        $options = $var['options'];
                        if (is_string($options)) {
                            $options = json_decode($options, true) ?? [];
                        }
                        if (is_array($options)) {
                            foreach ($options as $j => $opt) {
                                if (isset($opt['name'])) {
                                    $options[$j]['name'] = $this->normalizeMultilingualInput($opt['name']);
                                }
                            }
                            $variations[$i]['options'] = $options;
                        }
                    }
                }
                $dataToMerge['variations'] = $variations;
            }
        }

        if ($request->has('recipes')) {
            $recipes = $request->input('recipes');
            if (is_string($recipes)) {
                $decoded = json_decode($recipes, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $recipes = $decoded;
                }
            }
            if (is_array($recipes)) {
                $dataToMerge['recipes'] = $recipes;
            }
        }

        if ($request->has('option_recipes')) {
            $optionRecipes = $request->input('option_recipes');
            if (is_string($optionRecipes)) {
                $decoded = json_decode($optionRecipes, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $optionRecipes = $decoded;
                }
            }
            if (is_array($optionRecipes)) {
                $dataToMerge['option_recipes'] = $optionRecipes;
            }
        }

        if (! empty($dataToMerge)) {
            $request->merge($dataToMerge);
        }
    }
}
