<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Addon;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\Delivery;
use App\Models\FinancialAccount;
use App\Models\HallTable;
use App\Models\Order;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use App\Services\KitchenDispatchService;
use App\Services\RestaurantWorkingHoursService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        protected RestaurantWorkingHoursService $workingHoursService,
        protected KitchenDispatchService $kitchenDispatch,
    ) {}

    private function orderQuery(): Builder
    {
        return Order::with([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ])->latest('id');
    }

    private function applyDateFilters(Builder $query, Request $request): Builder
    {
        if ($request->query('date') === 'all' || $request->boolean('all_dates') || $request->query('all') === 'true') {
            return $query;
        }

        if ($request->filled('date')) {
            $range = $this->workingHoursService->getRangeForDate($request->query('date'));

            return $query->whereBetween('created_at', [$range['start'], $range['end']]);
        }

        if ($request->filled('from_date') || $request->filled('start_date')) {
            $fromDate = $request->query('from_date') ?? $request->query('start_date');
            $toDate = $request->query('to_date') ?? $request->query('end_date') ?? $fromDate;
            $range = $this->workingHoursService->getRangeBetweenDates($fromDate, $toDate);

            return $query->whereBetween('created_at', [$range['start'], $range['end']]);
        }

        $range = $this->workingHoursService->getTodayAndYesterdayRange();

        return $query->where('created_at', '>=', $range['start']);
    }

    public function selectOptions(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'shifts' => Shift::select('id', 'name', 'branch_id', 'start_time', 'end_time')->get(),
                'branches' => Branch::select('id', 'name')->get(),
                'cashiers' => Cashier::select('id', 'name', 'branch_id')->get(),
                'cashier_men' => CashierMan::select('id', 'name', 'branch_id', 'shift_id')->get(),
                'hall_tables' => HallTable::select('id', 'name', 'branch_id', 'hall_id')->get(),
                'financial_accounts' => FinancialAccount::select('id', 'name', 'balance', 'status', 'is_cash', 'branch_id')->get(),
                'payment_methods' => PaymentMethod::select('id', 'name', 'icon', 'status', 'is_cash')->get(),
                'deliveries' => Delivery::select('id', 'name', 'phone', 'branch_id')->get(),
                'products' => Product::with(['variations.options'])->select('id', 'name', 'price')->get(),
                'addons' => Addon::select('id', 'name', 'price')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Filter by order type: pos (true) or online (false).
             *
             * @var string|null
             *
             * @example "false"
             */
            'is_pos' => 'sometimes|nullable|string',

            /**
             * Filter by shift ID.
             *
             * @var int|null
             */
            'shift_id' => 'sometimes|nullable|integer|exists:shifts,id',

            /**
             * Filter by order module.
             *
             * @var string|null
             *
             * @example "delivery"
             */
            'module' => 'sometimes|nullable|string|in:takeaway,dinein,delivery',

            /**
             * Specific business date filter (YYYY-MM-DD) or 'all' for full history.
             *
             * @var string|null
             *
             * @example "2026-10-03"
             */
            'date' => 'sometimes|nullable|string',

            /**
             * Start date for range filtering (YYYY-MM-DD).
             *
             * @var string|null
             *
             * @example "2026-10-01"
             */
            'from_date' => 'sometimes|nullable|date',

            /**
             * End date for range filtering (YYYY-MM-DD).
             *
             * @var string|null
             *
             * @example "2026-10-03"
             */
            'to_date' => 'sometimes|nullable|date',

            /**
             * Alias for start date range filtering.
             *
             * @var string|null
             */
            'start_date' => 'sometimes|nullable|date',

            /**
             * Alias for end date range filtering.
             *
             * @var string|null
             */
            'end_date' => 'sometimes|nullable|date',

            /**
             * Bypass date filtering to fetch all historical orders.
             *
             * @var bool|null
             */
            'all_dates' => 'sometimes|nullable|boolean',

            /**
             * Page number for pagination.
             *
             * @var int|null
             *
             * @example 1
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Current page number alias.
             *
             * @var int|null
             */
            'current_page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             *
             * @example 15
             */
            'per_page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page alias.
             *
             * @var int|null
             */
            'perPage' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page alias.
             *
             * @var int|null
             */
            'pageSize' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page alias.
             *
             * @var int|null
             */
            'limit' => 'sometimes|nullable|integer|min:1',

            /**
             * Response language (ar or en).
             *
             * @var string|null
             *
             * @example "ar"
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $query = $this->orderQuery();

        if ($request->filled('is_pos') && ! in_array($request->query('is_pos'), ['all', 'undefined', 'null'], true)) {
            $isPos = filter_var($request->query('is_pos'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isPos !== null) {
                $query->where('is_pos', $isPos);
            }
        }

        if ($request->filled('shift_id') && ! in_array($request->query('shift_id'), ['all', 'undefined', 'null'], true)) {
            $query->where('shift_id', $request->query('shift_id'));
        }

        if ($request->filled('module') && ! in_array($request->query('module'), ['all', 'undefined', 'null'], true)) {
            $query->where('module', $request->query('module'));
        }

        $this->applyDateFilters($query, $request);

        $perPage = (int) ($validated['per_page']
            ?? $validated['perPage']
            ?? $validated['limit']
            ?? $validated['pageSize']
            ?? 15);

        $page = (int) ($validated['page']
            ?? $validated['current_page']
            ?? $request->input('currentPage')
            ?? $request->input('p')
            ?? 1);

        $orders = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page
        )->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Check difference of today and yesterday online orders count against client count.
     */
    public function checkNewOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Current count of orders loaded on the client side for today and yesterday.
             *
             * @var int|null
             *
             * @example 10
             */
            'count' => 'sometimes|nullable|integer|min:0',

            /**
             * Client count alias.
             *
             * @var int|null
             */
            'client_count' => 'sometimes|nullable|integer|min:0',

            /**
             * Orders count alias.
             *
             * @var int|null
             */
            'orders_count' => 'sometimes|nullable|integer|min:0',
        ]);

        $clientCount = (int) ($validated['count']
            ?? $validated['client_count']
            ?? $validated['orders_count']
            ?? 0);

        $range = $this->workingHoursService->getTodayAndYesterdayRange();

        $query = Order::query()
            ->where('is_pos', false)
            ->where('created_at', '>=', $range['start']);

        $serverCount = $query->count();
        $difference = max(0, $serverCount - $clientCount);

        $orderIds = [];
        if ($difference > 0) {
            $orderIds = $query->latest('id')
                ->take($difference)
                ->pluck('id')
                ->values()
                ->all();
        }

        return response()->json([
            'status' => true,
            'server_count' => $serverCount,
            'client_count' => $clientCount,
            'difference' => $difference,
            'has_new' => $difference > 0,
            'order_ids' => $orderIds,
        ]);
    }

    public function posOrders(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'current_page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
            'perPage' => 'sometimes|nullable|integer|min:1',
            'pageSize' => 'sometimes|nullable|integer|min:1',
            'limit' => 'sometimes|nullable|integer|min:1',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $perPage = (int) ($validated['per_page']
            ?? $validated['perPage']
            ?? $validated['limit']
            ?? $validated['pageSize']
            ?? 15);

        $page = (int) ($validated['page']
            ?? $validated['current_page']
            ?? $request->input('currentPage')
            ?? $request->input('p')
            ?? 1);

        $orders = $this->orderQuery()
            ->where('is_pos', true)
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page
            )->withQueryString();

        return OrderResource::collection($orders);
    }

    public function onlineOrders(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'current_page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
            'perPage' => 'sometimes|nullable|integer|min:1',
            'pageSize' => 'sometimes|nullable|integer|min:1',
            'limit' => 'sometimes|nullable|integer|min:1',
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $perPage = (int) ($validated['per_page']
            ?? $validated['perPage']
            ?? $validated['limit']
            ?? $validated['pageSize']
            ?? 15);

        $page = (int) ($validated['page']
            ?? $validated['current_page']
            ?? $request->input('currentPage')
            ?? $request->input('p')
            ?? 1);

        $orders = $this->orderQuery()
            ->where('is_pos', false)
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page
            )->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $isPos = $request->has('is_pos')
            ? filter_var($request->input('is_pos'), FILTER_VALIDATE_BOOLEAN)
            : true;

        $validated = $request->validate([
            'shift_id' => 'nullable|exists:shifts,id',
            'cashier_id' => 'nullable|exists:cashiers,id',
            'cashier_man_id' => 'nullable|exists:cashier_men,id',
            'hall_table_id' => 'nullable|exists:hall_tables,id',
            'captain_order_id' => 'nullable|exists:captain_orders,id',
            'financial_account_id' => [
                $isPos ? 'required_without:financial_id' : 'nullable',
                'integer',
                'exists:financial_accounts,id',
            ],
            'financial_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'payment_method_id' => [
                $isPos ? 'nullable' : 'required',
                'integer',
                'exists:payment_methods,id',
            ],
            'paid' => 'nullable|boolean',
            'module' => 'required|in:takeaway,dinein,delivery',
            'address' => 'nullable|string',
            'note' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:255',
            'is_pos' => 'nullable|boolean',
            'total' => 'required|numeric|min:0',
            'total_tax' => 'nullable|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'final_price' => 'required|numeric|min:0',

            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|exists:products,id',
            'products.*.price' => 'required_with:products|numeric|min:0',
            'products.*.note' => 'nullable|string',

            'products.*.variations' => 'nullable|array',
            'products.*.variations.*.variation_id' => 'required_with:products.*.variations|exists:variations,id',
            'products.*.variations.*.options' => 'nullable|array',
            'products.*.variations.*.options.*.option_id' => 'required_with:products.*.variations.*.options|exists:options,id',
            'products.*.variations.*.options.*.price' => 'required_with:products.*.variations.*.options|numeric|min:0',

            'products.*.addons' => 'nullable|array',
            'products.*.addons.*.addon_id' => 'required_with:products.*.addons|exists:addons,id',
            'products.*.addons.*.price' => 'required_with:products.*.addons|numeric|min:0',
        ]);

        $financialAccountId = $validated['financial_account_id'] ?? $validated['financial_id'] ?? null;
        $paymentMethodId = $validated['payment_method_id'] ?? null;

        $isCash = false;
        if ($financialAccountId) {
            $isCash = (bool) FinancialAccount::where('id', $financialAccountId)->value('is_cash');
        }
        if ($paymentMethodId) {
            $isCash = $isCash || (bool) PaymentMethod::where('id', $paymentMethodId)->value('is_cash');
        }

        $module = $validated['module'] ?? 'delivery';
        $isDelivery = ($module === 'delivery');

        $paid = array_key_exists('paid', $validated) && ! is_null($validated['paid'])
            ? (bool) $validated['paid']
            : (! ($isCash && $isDelivery));

        $order = DB::transaction(function () use ($validated, $financialAccountId, $paymentMethodId, $paid, $isPos): Order {
            $order = Order::create([
                'shift_id' => $validated['shift_id'] ?? null,
                'cashier_id' => $validated['cashier_id'] ?? null,
                'cashier_man_id' => $validated['cashier_man_id'] ?? null,
                'hall_table_id' => $validated['hall_table_id'] ?? null,
                'captain_order_id' => $validated['captain_order_id'] ?? null,
                'financial_account_id' => $financialAccountId,
                'delivery_id' => $validated['delivery_id'] ?? null,
                'payment_method_id' => $paymentMethodId,
                'paid' => $paid,
                'module' => $validated['module'],
                'address' => $validated['address'] ?? null,
                'note' => $validated['note'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'name' => $validated['name'] ?? null,
                'is_pos' => $isPos,
                'status' => $validated['module'] === 'dinein' ? 'pickup' : 'preparing',
                'total' => $validated['total'],
                'total_tax' => $validated['total_tax'] ?? 0,
                'total_discount' => $validated['total_discount'] ?? 0,
                'final_price' => $validated['final_price'],
            ]);

            foreach ($validated['products'] ?? [] as $productData) {
                $orderProduct = OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $productData['product_id'],
                    'quantity' => $productData['quantity'] ?? 1,
                    'price' => $productData['price'],
                    'note' => $productData['note'] ?? null,
                ]);

                foreach ($productData['variations'] ?? [] as $variationData) {
                    $orderVariation = OrderPVariation::create([
                        'order_product_id' => $orderProduct->id,
                        'variation_id' => $variationData['variation_id'],
                    ]);

                    foreach ($variationData['options'] ?? [] as $optionData) {
                        OrderPOption::create([
                            'order_p_variation_id' => $orderVariation->id,
                            'option_id' => $optionData['option_id'],
                            'price' => $optionData['price'],
                        ]);
                    }
                }

                foreach ($productData['addons'] ?? [] as $addonData) {
                    OrderPAddon::create([
                        'order_product_id' => $orderProduct->id,
                        'addon_id' => $addonData['addon_id'],
                        'price' => $addonData['price'],
                    ]);
                }
            }

            return $order;
        });

        $order->load([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        // Dispatch to kitchen queues (skipped for dinein which is handled at cart level)
        if ($order->module !== 'dinein') {
            $this->kitchenDispatch->dispatchFromOrder($order);
        }

        return response()->json([
            'status' => true,
            'message' => 'Order created successfully.',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'financialAccount',
            'delivery',
            'paymentMethod',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update order status with module-specific allowed transitions.
     *
     * Delivery  → done, out_of_delivery, delivered, return, failed_to_delivered
     * Takeaway  → done, pickup, return
     * Dinein    → return
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $allowedStatuses = Order::$allowedStatusTransitions[$order->module] ?? [];

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $allowedStatuses)],
            'delivery_id' => [
                Rule::requiredIf(fn () => $order->module === 'delivery' && $request->input('status') === 'out_of_delivery'),
                'nullable',
                'integer',
                'exists:deliveries,id',
            ],
        ]);

        $updateData = ['status' => $validated['status']];
        if (array_key_exists('delivery_id', $validated) && ! is_null($validated['delivery_id'])) {
            $updateData['delivery_id'] = $validated['delivery_id'];
        }

        $order->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully.',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'delivery_id' => $order->delivery_id,
            ],
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }
}
