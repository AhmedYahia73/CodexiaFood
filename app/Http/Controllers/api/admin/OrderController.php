<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Addon;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\HallTable;
use App\Models\Order;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function orderQuery(): Builder
    {
        return Order::with([
            'shift',
            'cashier',
            'cashierMan',
            'hallTable',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ])->latest();
    }

    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'shifts' => Shift::select('id', 'name', 'branch_id', 'start_time', 'end_time')->get(),
                'branches' => Branch::select('id', 'name')->get(),
                'cashiers' => Cashier::select('id', 'name', 'branch_id')->get(),
                'cashier_men' => CashierMan::select('id', 'name', 'branch_id', 'shift_id')->get(),
                'hall_tables' => HallTable::select('id', 'name', 'branch_id', 'hall_id')->get(),
                'products' => Product::with(['variations.options'])->select('id', 'name', 'price')->get(),
                'addons' => Addon::select('id', 'name', 'price')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->orderQuery();

        if ($request->has('is_pos')) {
            $isPos = filter_var($request->query('is_pos'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_pos', $isPos);
        }

        if ($request->has('shift_id')) {
            $query->where('shift_id', $request->query('shift_id'));
        }

        if ($request->has('module')) {
            $query->where('module', $request->query('module'));
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function posOrders(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderQuery()
            ->where('is_pos', true)
            ->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function onlineOrders(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderQuery()
            ->where('is_pos', false)
            ->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'nullable|exists:shifts,id',
            'cashier_id' => 'nullable|exists:cashiers,id',
            'cashier_man_id' => 'nullable|exists:cashier_men,id',
            'hall_table_id' => 'nullable|exists:hall_tables,id',
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

        $order = DB::transaction(function () use ($validated): Order {
            $order = Order::create([
                'shift_id' => $validated['shift_id'] ?? null,
                'cashier_id' => $validated['cashier_id'] ?? null,
                'cashier_man_id' => $validated['cashier_man_id'] ?? null,
                'hall_table_id' => $validated['hall_table_id'] ?? null,
                'module' => $validated['module'],
                'address' => $validated['address'] ?? null,
                'note' => $validated['note'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'name' => $validated['name'] ?? null,
                'is_pos' => $validated['is_pos'] ?? true,
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
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

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

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }
}
