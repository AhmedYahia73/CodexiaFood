<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Category;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Product;
use App\Models\StartShift;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierHomeController extends Controller
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
        $locale = $this->getLocale($request);

        $query = Category::whereNotNull('category_id')->where('status', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
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

    public function cashiers(Request $request): JsonResponse
    {
        $data = Cashier::
        where("branch_id", $request->user()->branch_id)
        ->whereNull('cashier_man_id')
        ->get()
            ->map(fn (Cashier $cashier) => [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ]);

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get products filterable by category_id or sub_category_id.
     */
    public function products(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $query = Product::with(['discount', 'tax'])->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('sub_category_id')) {
            $query->where('sub_category_id', $request->query('sub_category_id'));
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
     * Get halls.
     */
    public function halls(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $halls = Hall::where('status', true)
            ->latest()
            ->get()
            ->map(fn (Hall $hall) => [
                'id' => $hall->id,
                'name' => $this->getLocalized($hall->name, $locale),
                'branch_id' => $hall->branch_id,
                'status' => (bool) $hall->status,
            ]);

        return response()->json([
            'status' => true,
            'data' => $halls,
        ]);
    }

    /**
     * Get hall tables filterable by hall_id.
     */
    public function hallTables(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $query = HallTable::where('status', true);

        if ($request->filled('hall_id')) {
            $query->where('hall_id', $request->query('hall_id'));
        }

        $tables = $query->get()->map(fn (HallTable $table) => [
            'id' => $table->id,
            'name' => $this->getLocalized($table->name, $locale),
            'hall_id' => $table->hall_id,
            'branch_id' => $table->branch_id,
            'status' => (bool) $table->status,
            'qr' => $this->formatImageUrl($table->qr),
        ]);

        return response()->json([
            'status' => true,
            'data' => $tables,
        ]);
    }

    /**
     * Start a new shift for the cashier.
     */
    public function startShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cashier_id' => 'required|exists:cashiers,id',
            'cashier_man_id' => 'nullable|exists:cashier_men,id',
        ]);

        $cashierMan = auth()->user();
        $cashierManId = $request->input('cashier_man_id') ?? $cashierMan?->id;
        $branchId = $cashierMan?->branch_id ?? $request->input('branch_id');

        $hasOpenShift = StartShift::where('cashier_man_id', $cashierManId)
            ->where('branch_id', $branchId)
            ->whereNull('end')
            ->exists();

        if ($hasOpenShift) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى غلق الشيفت السابق اولا',
            ]);
        }

        if ($cashierMan) {
            $cashierMan->update([
                'cashier_id' => $validated['cashier_id'],
            ]);
        }

        $startShift = StartShift::create([
            'start' => now(),
            'end' => null,
            'branch_id' => $branchId,
            'cashier_id' => $validated['cashier_id'],
            'cashier_man_id' => $cashierManId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم بدء الشيفت بنجاح',
            'data' => $startShift->load(['branch', 'cashier', 'cashierMan']),
        ], 201);
    }

    /**
     * Start a new shift for the cashier.
     */
    public function checkStartShift(Request $request): JsonResponse
    { 

        $cashierMan = auth()->user();
        $branchId = $cashierMan?->branch_id ?? $request->input('branch_id');

        $hasOpenShift = StartShift::where('cashier_man_id', $cashierMan->id)
            ->where('branch_id', $branchId)
            ->whereNull('end')
            ->exists();

        if ($hasOpenShift) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى غلق الشيفت السابق اولا',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'تقدر تبدأ الشيفت',
        ], 201);
    }

    /**
     * End the current open shift for the cashier.
     */
    public function endShift(Request $request): JsonResponse
    {
        $cashierMan = auth()->user();
        $cashierManId = $request->input('cashier_man_id') ?? $cashierMan?->id;
        $branchId = $cashierMan?->branch_id ?? $request->input('branch_id');

        $openShift = StartShift::where('cashier_man_id', $cashierManId)
            ->where('branch_id', $branchId)
            ->whereNull('end')
            ->latest('start')
            ->first();

        if (! $openShift) {
            return response()->json([
                'status' => false,
                'message' => 'لا يوجد شيفت مفتوح حالياً',
            ], 400);
        }

        $openShift->update([
            'end' => now(),
        ]);

        if ($cashierMan) {
            $cashierMan->update([
                'cashier_id' => null,
            ]);
        } elseif ($cashierManId) {
            CashierMan::where('id', $cashierManId)->update([
                'cashier_id' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إنهاء الشيفت بنجاح',
            'data' => $openShift->fresh(['branch', 'cashier', 'cashierMan']),
        ]);
    }
}
