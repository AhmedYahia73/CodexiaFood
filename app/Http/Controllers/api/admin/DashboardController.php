<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display dashboard annual statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) now()->year;

        // 1 & 2: Total orders count and sum of final_price during the year
        $ordersSummary = Order::query()
            ->whereYear('created_at', $year)
            ->toBase()
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(final_price), 0) as total_final_price')
            ->first();

        $totalOrders = (int) ($ordersSummary->total_orders ?? 0);
        $totalFinalPrice = (float) ($ordersSummary->total_final_price ?? 0);

        // 3: Top 5 products sold during the year (OrderProduct => quantity)
        $topProducts = OrderProduct::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('order_id', Order::select('id')->whereYear('created_at', $year))
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->with('product')
            ->get()
            ->map(function (OrderProduct $item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'total_quantity' => (int) $item->total_quantity,
                    'product' => $item->product ? new ProductResource($item->product) : null,
                ];
            });

        // 4 & 5: Monthly breakdown for all 12 months (Orders count & sum of final_price)
        $driver = DB::connection()->getDriverName();
        $monthExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(MONTH FROM created_at)',
            default => 'MONTH(created_at)',
        };

        $monthlyStats = Order::query()
            ->whereYear('created_at', $year)
            ->toBase()
            ->selectRaw("{$monthExpression} as month, COUNT(*) as orders_count, COALESCE(SUM(final_price), 0) as total_final_price")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthNames = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        $monthlyOrders = [];
        $monthlyFinalPrice = [];
        $monthlyOrdersCountsOnly = [];
        $monthlyFinalPricesOnly = [];

        for ($m = 1; $m <= 12; $m++) {
            $stat = $monthlyStats->get($m);
            $ordersCount = (int) ($stat->orders_count ?? 0);
            $finalPrice = round((float) ($stat->total_final_price ?? 0), 2);

            $monthlyOrders[] = [
                'month' => $m,
                'month_name' => $monthNames[$m],
                'orders_count' => $ordersCount,
            ];

            $monthlyFinalPrice[] = [
                'month' => $m,
                'month_name' => $monthNames[$m],
                'total_final_price' => $finalPrice,
            ];

            $monthlyOrdersCountsOnly[] = $ordersCount;
            $monthlyFinalPricesOnly[] = $finalPrice;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'year' => $year,
                'total_orders' => $totalOrders,
                'total_final_price' => round($totalFinalPrice, 2),
                'top_products' => $topProducts,
                'monthly_orders' => $monthlyOrders,
                'monthly_final_price' => $monthlyFinalPrice,
                'chart' => [
                    'months' => array_values($monthNames),
                    'orders_count' => $monthlyOrdersCountsOnly,
                    'final_price' => $monthlyFinalPricesOnly,
                ],
            ],
        ]);
    }

    /**
     * Alias for index.
     */
    public function statistics(Request $request): JsonResponse
    {
        return $this->index($request);
    }
}
