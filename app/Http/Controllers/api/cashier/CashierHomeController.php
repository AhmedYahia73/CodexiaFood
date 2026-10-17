<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSetupResource;
use App\Http\Resources\StartShiftResource;
use App\Models\Addon;
use App\Models\BusinessSetup;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Hall;
use App\Models\HallTable;
use App\Models\Order;
use App\Models\OrderCart;
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
        $request->validate([
            'lang' => 'sometimes|nullable|string|in:ar,en',
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
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'lang' => 'sometimes|nullable|string|in:ar,en',
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

    public function cashiers(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
        ]);

        $data = Cashier::where('branch_id', $request->user()->branch_id)
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
        $validated = $request->validate([
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'sub_category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'lang' => 'sometimes|nullable|string|in:ar,en',
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
            'lang' => 'sometimes|nullable|string|in:ar,en',
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
            'lang' => 'sometimes|nullable|string|in:ar,en',
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
     * Get halls.
     */
    public function halls(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $cashierBranchId = auth()->user()?->branch_id;

        $halls = Hall::where('status', true)
            ->when($cashierBranchId, fn ($q) => $q->where(function ($sub) use ($cashierBranchId) {
                $sub->where('branch_id', $cashierBranchId)->orWhereNull('branch_id');
            }))
            ->latest()
            ->get()
            ->map(fn (Hall $hall) => [
                'id' => $hall->id,
                'name' => $this->getLocalized($hall->name, $locale),
                'branch_id' => $hall->branch_id,
                'status' => (bool) $hall->status,
                'tables_count' => HallTable::where('hall_id', $hall->id)->where('status', true)->count(),
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
        $validated = $request->validate([
            /**
             * Hall ID to filter tables.
             *
             * @var int|null
             *
             * @example 1
             */
            'hall_id' => 'sometimes|nullable|integer|exists:halls,id',

            /**
             * Language code (ar or en).
             *
             * @var string|null
             *
             * @example ar
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $query = HallTable::where('status', true);

        if (! empty($validated['hall_id'])) {
            $query->where('hall_id', $validated['hall_id']);
        }

        $tables = $query->get()->map(fn (HallTable $table) => [
            'id' => $table->id,
            'name' => $this->getLocalized($table->name, $locale),
            'hall_id' => $table->hall_id,
            'branch_id' => $table->branch_id,
            'status' => (bool) $table->status,
            'qr' => $this->formatImageUrl($table->qr),
            'has_active_cart' => OrderCart::where('hall_table_id', $table->id)->exists(),
            'cart_items_count' => OrderCart::where('hall_table_id', $table->id)->count(),
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
            /**
             * Target cashier POS device ID.
             *
             * @var int
             *
             * @example 1
             */
            'cashier_id' => 'required|exists:cashiers,id',
            /**
             * Optional cashier man user ID.
             *
             * @var int|null
             */
            'cashier_man_id' => 'sometimes|nullable|exists:cashier_men,id',
            /**
             * Optional branch ID.
             *
             * @var int|null
             */
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            /**
             * Opening cash amount in the drawer at the start of shift.
             *
             * @var float|null
             *
             * @example 200.00
             */
            'start_amount' => 'sometimes|nullable|numeric|min:0',
            /**
             * Alias for start_amount.
             *
             * @var float|null
             *
             * @example 200.00
             */
            'start_mony' => 'sometimes|nullable|numeric|min:0',
            /**
             * The financial account associated with the shift opening.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_account_id' => 'sometimes|nullable|exists:financial_accounts,id',
            /**
             * Alias for financial_account_id.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_id' => 'sometimes|nullable|exists:financial_accounts,id',
        ]);

        $cashierMan = auth()->user();
        $cashierManId = $request->input('cashier_man_id') ?? $cashierMan?->id;
        $branchId = $cashierMan?->branch_id ?? $request->input('branch_id');

        $cashier = Cashier::where('cashier_man_id', $cashierManId)->first();
        if ($cashier && $cashier->id != $validated['cashier_id']) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الكاشير مرتبط بكاشير مان اخر',
            ], 400);
        }

        $targetCashier = Cashier::find($validated['cashier_id']);
        if ($targetCashier && $targetCashier->cashier_man_id && $targetCashier->cashier_man_id != $cashierManId) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الكاشير مرتبط بكاشير مان اخر',
            ], 400);
        }

        $hasOpenShift = StartShift::where('cashier_man_id', $cashierManId)
            ->where('branch_id', $branchId)
            ->whereNull('end')
            ->exists();

        if ($hasOpenShift) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى غلق الشيفت السابق اولا',
            ], 400);
        }

        if ($cashierMan) {
            $cashierMan->update([
                'cashier_id' => $validated['cashier_id'],
            ]);
        }

        Cashier::where('id', $validated['cashier_id'])->update([
            'cashier_man_id' => $cashierManId,
        ]);

        $startAmount = round((float) ($request->input('start_amount') ?? $request->input('start_mony') ?? 0), 2);
        $financialAccountId = $request->input('financial_account_id') ?? $request->input('financial_id');

        $startShift = StartShift::create([
            'start' => now(),
            'end' => null,
            'branch_id' => $branchId,
            'cashier_id' => $validated['cashier_id'],
            'cashier_man_id' => $cashierManId,
            'start_amount' => $startAmount,
            'financial_account_id' => $financialAccountId,
            'default_total_amount' => 0,
            'total_mony' => null,
            'orders_total' => 0,
            'expenses_total' => 0,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم بدء الشيفت بنجاح',
            'data' => new StartShiftResource($startShift->load(['branch', 'cashier', 'cashierMan', 'financialAccount'])),
        ], 201);
    }

    /**
     * Start a new shift for the cashier.
     */
    public function checkStartShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|nullable|exists:branches,id',
        ]);

        $cashierMan = auth()->user();
        $branchId = $validated['branch_id'] ?? $cashierMan?->branch_id;

        $hasOpenShift = StartShift::where('cashier_man_id', $cashierMan->id)
            ->where('branch_id', $branchId)
            ->whereNull('end')
            ->exists();

        if ($hasOpenShift) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى غلق الشيفت السابق اولا',
            ]);
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
        $validated = $request->validate([
            /**
             * The actual cash counted in drawer by the cashier at shift end.
             *
             * @var float
             *
             * @example 1500.00
             */
            'total_mony' => 'required|numeric|min:0',
            /**
             * Optional branch ID filter.
             *
             * @var int|null
             */
            'branch_id' => 'sometimes|nullable|exists:branches,id',
        ]);

        $totalMony = round((float) $validated['total_mony'], 2);
        $cashierMan = auth()->user();
        $cashierManId = $cashierMan?->id;
        $branchId = $validated['branch_id'] ?? $cashierMan?->branch_id;

        $openShift = StartShift::where('cashier_man_id', $cashierManId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('end')
            ->latest('start')
            ->first();

        if (! $openShift) {
            return response()->json([
                'status' => false,
                'message' => 'لا يوجد شيفت مفتوح حالياً',
            ], 400);
        }

        $endTime = now();

        // 1. Calculate orders total during shift: is_pos = true, status in ('delivered', 'pickup')
        $ordersQuery = Order::query()
            ->where('is_pos', true)
            ->whereIn('status', ['delivered', 'pickup'])
            ->where(function ($query) use ($openShift, $endTime) {
                $query->where('shift_id', $openShift->id)
                    ->orWhere(function ($q) use ($openShift, $endTime) {
                        $q->where('cashier_man_id', $openShift->cashier_man_id)
                            ->where('cashier_id', $openShift->cashier_id)
                            ->whereBetween('created_at', [$openShift->start, $endTime]);
                    });
            });

        $ordersTotal = round((float) $ordersQuery->sum('final_price'), 2);
        if ($ordersTotal == 0) {
            $ordersTotal = round((float) $ordersQuery->sum('total'), 2);
        }

        // 2. Calculate expenses total during shift for this cashier_man
        $expensesQuery = Expense::query()
            ->where(function ($query) use ($openShift, $endTime) {
                $query->where('shift_id', $openShift->id)
                    ->orWhere(function ($q) use ($openShift, $endTime) {
                        $q->where('cashier_man_id', $openShift->cashier_man_id)
                            ->whereBetween('created_at', [$openShift->start, $endTime]);
                    });
            });

        $expensesTotal = round((float) $expensesQuery->sum('amount'), 2);

        // 3. Starting cash amount entered at start_shift
        $startAmount = round((float) ($openShift->start_amount ?? 0), 2);

        // 4. Expected drawer cash = start_amount + orders_total - expenses_total
        // Deficit = default_total_amount - total_mony = orders_total - total_mony - expenses_total + start_amount
        $defaultTotalAmount = round($startAmount + $ordersTotal - $expensesTotal, 2);

        Cashier::where('cashier_man_id', $cashierManId)->update([
            'cashier_man_id' => null,
        ]);

        $openShift->update([
            'end' => $endTime,
            'start_amount' => $startAmount,
            'orders_total' => $ordersTotal,
            'expenses_total' => $expensesTotal,
            'default_total_amount' => $defaultTotalAmount,
            'total_mony' => $totalMony,
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
            'data' => new StartShiftResource($openShift->fresh(['branch', 'cashier', 'cashierMan', 'financialAccount'])),
        ]);
    }

    /**
     * Get active financial accounts for cashier.
     */
    public function financialAccounts(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Language code ('ar' or 'en').
             *
             * @var string|null
             *
             * @example "ar"
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
            /**
             * Filter by branch id.
             *
             * @var int|null
             */
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
        ]);

        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $branchId = $request->query('branch_id') ?? auth()->user()?->branch_id;

        $accounts = FinancialAccount::query()
            ->where('status', true)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (FinancialAccount $acc) use ($locale) {
                $name = $acc->name;
                $localizedName = is_array($name)
                    ? ($name[$locale] ?? $name['ar'] ?? $name['en'] ?? '')
                    : (string) $name;

                $iconUrl = null;
                if ($acc->icon) {
                    $iconUrl = (str_starts_with($acc->icon, 'http://') || str_starts_with($acc->icon, 'https://'))
                        ? $acc->icon
                        : url('storage/'.ltrim($acc->icon, '/'));
                }

                return [
                    'id' => $acc->id,
                    'name' => $localizedName,
                    'icon' => $iconUrl,
                    'balance' => (float) $acc->balance,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'تم جلب الحسابات المالية بنجاح',
            'data' => $accounts,
        ]);
    }

    /**
     * Get business setup details for cashier.
     */
    public function businessSetup(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $businessSetup = BusinessSetup::first();

        return response()->json([
            'status' => true,
            'data' => $businessSetup ? new BusinessSetupResource($businessSetup) : null,
        ]);
    }
}
