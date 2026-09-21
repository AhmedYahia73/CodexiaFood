<?php

namespace App\Http\Controllers\api\table;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Category;
use App\Models\HallTable;
use App\Models\Product;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableHomeController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator
    ) {}

    private function getLocale(Request $request): string
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();

        return str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
    }

    private function getLocalized(mixed $value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ar'] ?? $value['en'] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    private function formatImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return url('storage/'.ltrim($image, '/'));
    }

    /**
     * Get parent categories (where category_id is null).
     */
    public function parentCategories(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $categories = Category::whereNull('category_id')
            ->where('status', true)
            ->latest()
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $this->getLocalized($category->name, $locale),
                'description' => $this->getLocalized($category->description, $locale),
                'image' => $this->formatImageUrl($category->image),
                'status' => (bool) $category->status,
                'type' => $category->type,
            ]);

        return response()->json([
            'status' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get sub categories (where category_id is not null), optional filter by category_id.
     */
    public function subCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $query = Category::whereNotNull('category_id')->where('status', true);

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $categories = $query->latest()
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'category_id' => $category->category_id,
                'name' => $this->getLocalized($category->name, $locale),
                'description' => $this->getLocalized($category->description, $locale),
                'image' => $this->formatImageUrl($category->image),
                'status' => (bool) $category->status,
                'type' => $category->type,
            ]);

        return response()->json([
            'status' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get products filterable by category_id or sub_category_id.
     */
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $query = Product::with(['discount', 'tax'])->latest();

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (! empty($validated['sub_category_id'])) {
            $query->where('sub_category_id', $validated['sub_category_id']);
        }

        $products = $query->get()->map(function (Product $product) use ($locale) {
            $pricing = $this->priceCalculator->calculateProduct($product);

            return [
                'id' => $product->id,
                'name' => $this->getLocalized($product->name, $locale),
                'description' => $this->getLocalized($product->description, $locale),
                'image' => $this->formatImageUrl($product->image),
                'category_id' => $product->category_id,
                'sub_category_id' => $product->sub_category_id,
                'stock' => (int) $product->stock,
                'price' => $pricing['price'],
                'discount_val' => $pricing['discount_val'],
                'tax_val' => $pricing['tax_val'],
                'final_price' => $pricing['final_price'],
                'discount' => $product->discount ? [
                    'id' => $product->discount->id,
                    'name' => $this->getLocalized($product->discount->name, $locale),
                    'type' => $product->discount->type,
                    'amount' => (float) $product->discount->amount,
                ] : null,
                'tax' => $product->tax ? [
                    'id' => $product->tax->id,
                    'name' => $this->getLocalized($product->tax->name, $locale),
                    'type' => $product->tax->type,
                    'amount' => (float) $product->tax->amount,
                ] : null,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get product details with variations and options with their calculated prices.
     */
    public function productDetails(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $product->load(['discount', 'tax', 'variations.options']);

        $productPricing = $this->priceCalculator->calculateProduct($product);

        $variations = $product->variations->map(function ($variation) use ($product, $locale) {
            $options = $variation->options->map(function ($option) use ($product, $locale) {
                $optionPricing = $this->priceCalculator->calculateOption(
                    $option,
                    $product->discount,
                    $product->tax
                );

                return [
                    'id' => $option->id,
                    'name' => $this->getLocalized($option->name, $locale),
                    'price' => $optionPricing['price'],
                    'discount_val' => $optionPricing['discount_val'],
                    'tax_val' => $optionPricing['tax_val'],
                    'final_price' => $optionPricing['final_price'],
                    'status' => (bool) $option->status,
                ];
            });

            return [
                'id' => $variation->id,
                'name' => $this->getLocalized($variation->name, $locale),
                'status' => (bool) $variation->status,
                'required' => (bool) $variation->required,
                'options' => $options,
            ];
        });

        $data = [
            'id' => $product->id,
            'name' => $this->getLocalized($product->name, $locale),
            'description' => $this->getLocalized($product->description, $locale),
            'image' => $this->formatImageUrl($product->image),
            'category_id' => $product->category_id,
            'sub_category_id' => $product->sub_category_id,
            'stock' => (int) $product->stock,
            'price' => $productPricing['price'],
            'discount_val' => $productPricing['discount_val'],
            'tax_val' => $productPricing['tax_val'],
            'final_price' => $productPricing['final_price'],
            'variations' => $variations,
        ];

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get all addons with discount_val, tax_val, final_price.
     */
    public function addons(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $addons = Addon::with(['discount', 'tax'])
            ->get()
            ->map(function (Addon $addon) use ($locale) {
                $pricing = $this->priceCalculator->calculateAddon($addon);

                return [
                    'id' => $addon->id,
                    'name' => $this->getLocalized($addon->name, $locale),
                    'image' => $this->formatImageUrl($addon->image),
                    'price' => $pricing['price'],
                    'discount_val' => $pricing['discount_val'],
                    'tax_val' => $pricing['tax_val'],
                    'final_price' => $pricing['final_price'],
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $addons,
        ]);
    }

    /**
     * Get table information (for scanned QR code tableOrder/{table_code_or_id} or via request param ?table_code=).
     */
    public function tableInfo(Request $request, HallTable|string|null $hallTable = null): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
            'table_code' => 'nullable|string',
            'code' => 'nullable|string',
        ]);

        $table = null;

        if ($hallTable instanceof HallTable) {
            $table = $hallTable;
        } elseif (is_string($hallTable) && $hallTable !== '') {
            $table = HallTable::where('code', $hallTable)
                ->when(is_numeric($hallTable), fn ($q) => $q->orWhere('id', (int) $hallTable))
                ->first();
        }

        if (! $table) {
            $code = $request->input('table_code') ?? $request->input('code');
            if ($code) {
                $table = HallTable::where('code', $code)
                    ->when(is_numeric($code), fn ($q) => $q->orWhere('id', (int) $code))
                    ->first();
            }
        }

        if (! $table) {
            return response()->json([
                'status' => false,
                'message' => 'الطاولة غير موجودة',
            ], 404);
        }

        $locale = $this->getLocale($request);

        $table->load(['branch', 'hall']);

        $qrUrl = null;
        if ($table->qr) {
            $qrUrl = str_starts_with($table->qr, 'http')
                ? $table->qr
                : url('storage/'.ltrim($table->qr, '/'));
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $table->id,
                'table_code' => $table->code,
                'code' => $table->code,
                'name' => $table->name,
                'status' => (bool) $table->status,
                'qr' => $qrUrl,
                'branch' => $table->branch ? [
                    'id' => $table->branch->id,
                    'name' => $this->getLocalized($table->branch->name, $locale),
                ] : null,
                'hall' => $table->hall ? [
                    'id' => $table->hall->id,
                    'name' => $this->getLocalized($table->hall->name, $locale),
                ] : null,
            ],
        ]);
    }
}
