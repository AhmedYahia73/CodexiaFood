<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\BusinessSetup;
use App\Models\MaterialStock;
use App\Models\Order;
use App\Models\OrderCart;
use App\Models\OrderPAddon;
use App\Models\OrderPOption;
use App\Models\OrderProduct;
use App\Models\OrderPVariation;
use App\Models\ProductManufacturing;
use App\Models\ProductRecipeStock;
use App\Services\GeofenceService;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserOrderController extends Controller
{
    public function __construct(
        protected PriceCalculatorService $priceCalculator,
        protected GeofenceService $geofenceService
    ) {}

    private function getUuId(Request $request): ?string
    {
        $uuId = $request->input('uu_id')
            ?? $request->query('uu_id')
            ?? $request->header('uu_id')
            ?? $request->header('uu-id')
            ?? $request->header('X-UU-ID');

        return is_string($uuId) && trim($uuId) !== '' ? trim($uuId) : null;
    }

    /**
     * Calculate distance between two coordinate pairs using Haversine formula in kilometers.
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Find nearest branch to user coordinates and verify within BusinessSetup branch_cover range.
     *
     * @return array{branch: ?Branch, distance: ?float, max_cover: float, error: ?string}
     */
    private function resolveNearestBranch(float $userLat, float $userLng): array
    {
        $businessSetup = BusinessSetup::first();
        $maxCoverKm = (float) ($businessSetup?->branch_cover ?? 5.00);

        $branches = Branch::where('status', true)->get();
        if ($branches->isEmpty()) {
            return [
                'branch' => null,
                'distance' => null,
                'max_cover' => $maxCoverKm,
                'error' => 'لا توجد فروع نشطة متاحة حالياً',
            ];
        }

        $nearestBranch = null;
        $minDistance = null;

        foreach ($branches as $branch) {
            $points = $this->geofenceService->normalizePolygonPoints($branch->location);
            if (empty($points)) {
                continue;
            }

            // If point is directly inside branch polygon, distance is considered 0 km
            if (count($points) >= 3 && $this->geofenceService->isPointInPolygon($userLat, $userLng, $points)) {
                $distance = 0.00;
            } else {
                // Otherwise calculate distance to polygon centroid
                $centerLat = array_sum(array_column($points, 'lat')) / count($points);
                $centerLng = array_sum(array_column($points, 'lng')) / count($points);
                $distance = $this->calculateDistance($userLat, $userLng, $centerLat, $centerLng);
            }

            if ($minDistance === null || $distance < $minDistance) {
                $minDistance = $distance;
                $nearestBranch = $branch;
            }
        }

        if (! $nearestBranch) {
            return [
                'branch' => null,
                'distance' => null,
                'max_cover' => $maxCoverKm,
                'error' => 'لم يتم تحديد النطاق الجغرافي لأي فرع من الفروع المتاحة',
            ];
        }

        if ($minDistance > $maxCoverKm) {
            return [
                'branch' => $nearestBranch,
                'distance' => $minDistance,
                'max_cover' => $maxCoverKm,
                'error' => "عذراً، موقعك الحالي خارج نطاق التوصيل المتاح. المسافة لأقرب فرع ({$minDistance} كم) تتجاوز الحد الأقصى المسموح به ({$maxCoverKm} كم).",
            ];
        }

        return [
            'branch' => $nearestBranch,
            'distance' => $minDistance,
            'max_cover' => $maxCoverKm,
            'error' => null,
        ];
    }

    /**
     * Checkout user cart items and place an online order.
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! $request->has('uu_id') && $this->getUuId($request)) {
            $request->merge(['uu_id' => $this->getUuId($request)]);
        }

        $validated = $request->validate([
            /**
             * Unique identifier for the user / device.
             *
             * @var string
             *
             * @example "client-uuid-12345"
             */
            'uu_id' => 'required|string|max:255',

            /**
             * Latitude coordinate of delivery location.
             *
             * @var float
             *
             * @example 30.0444
             */
            'lat' => 'required|numeric|between:-90,90',

            /**
             * Longitude coordinate of delivery location.
             *
             * @var float
             *
             * @example 31.2357
             */
            'lng' => 'required|numeric|between:-180,180',

            /**
             * Full delivery address description.
             *
             * @var string
             *
             * @example "15 شارع النيل، المعادي، القاهرة"
             */
            'address' => 'required|string|max:500',

            /**
             * Customer contact phone number.
             *
             * @var string
             *
             * @example "01012345678"
             */
            'phone' => 'required|string|max:50',

            /**
             * Customer full name.
             *
             * @var string
             *
             * @example "أحمد محمد"
             */
            'name' => 'required|string|max:255',

            /**
             * Optional delivery notes or instructions.
             *
             * @var string|null
             *
             * @example "يرجى ترك الطلب عند الباب"
             */
            'note' => 'nullable|string|max:1000',

            /**
             * Order module (defaults to delivery).
             *
             * @var string
             *
             * @example "delivery"
             */
            'module' => 'nullable|string|in:delivery,takeaway,dinein',
        ]);

        $userLat = (float) $validated['lat'];
        $userLng = (float) $validated['lng'];

        // 1. Resolve nearest branch and check against branch_cover
        $nearestResult = $this->resolveNearestBranch($userLat, $userLng);
        if ($nearestResult['error'] !== null) {
            return response()->json([
                'status' => false,
                'message' => $nearestResult['error'],
                'distance' => $nearestResult['distance'],
                'max_cover' => $nearestResult['max_cover'],
            ], 422);
        }

        $branch = $nearestResult['branch'];
        $branchId = $branch->id;
        $uuId = $validated['uu_id'];

        // 2. Fetch cart items for current user uu_id
        $carts = OrderCart::with([
            'product.discount',
            'product.tax',
            'variationCarts.variation',
            'addonCarts.addon.discount',
            'addonCarts.addon.tax',
        ])
            ->where('uu_id', $uuId)
            ->get();

        if ($carts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'السلة فارغة، يرجى إضافة منتجات إلى السلة أولاً',
            ], 400);
        }

        // 3. Calculate item totals and grand totals using PriceCalculatorService
        $calculatedItems = $carts->map(
            fn (OrderCart $cart) => $this->priceCalculator->calculateCartItem($cart)
        )->all();

        $grandTotals = $this->priceCalculator->calculateGrandTotals($calculatedItems);

        // 4. Create Order inside DB transaction
        $order = DB::transaction(function () use ($validated, $carts, $grandTotals, $branchId, $uuId): Order {
            $order = Order::create([
                'shift_id' => null,
                'cashier_id' => null,
                'cashier_man_id' => null,
                'hall_table_id' => null,
                'branch_id' => $branchId,
                'module' => $validated['module'] ?? 'delivery',
                'address' => $validated['address'],
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'note' => $validated['note'] ?? null,
                'phone' => $validated['phone'],
                'name' => $validated['name'],
                'is_pos' => false,
                'total' => $grandTotals['grand_total_price'],
                'total_discount' => $grandTotals['grand_total_discount'],
                'total_tax' => $grandTotals['grand_total_tax'],
                'final_price' => $grandTotals['grand_final_price'],
            ]);

            foreach ($carts as $cart) {
                $qty = max(1, (int) $cart->quantity);

                $orderProduct = OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $cart->product_id,
                    'quantity' => $qty,
                    'price' => (float) $cart->product->price,
                    'note' => $cart->notes,
                ]);

                foreach ($cart->variationCarts as $varCart) {
                    $orderVariation = OrderPVariation::create([
                        'order_product_id' => $orderProduct->id,
                        'variation_id' => $varCart->variation_id,
                    ]);

                    $options = $varCart->getOptions();
                    foreach ($options as $option) {
                        OrderPOption::create([
                            'order_p_variation_id' => $orderVariation->id,
                            'option_id' => $option->id,
                            'price' => (float) $option->price,
                        ]);
                    }
                }

                foreach ($cart->addonCarts as $addonCart) {
                    if ($addonCart->addon) {
                        OrderPAddon::create([
                            'order_product_id' => $orderProduct->id,
                            'addon_id' => $addonCart->addon_id,
                            'price' => (float) $addonCart->addon->price,
                        ]);
                    }
                }

                // Deduct from branch stock for product ingredients based on ProductManufacturing
                if ($branchId) {
                    $spec = ProductManufacturing::with([
                        'productRecipeManufacturings.material',
                        'productRecipeManufacturings.productRecipe',
                    ])
                        ->where('product_id', $cart->product_id)
                        ->latest('id')
                        ->first();

                    if ($spec) {
                        foreach ($spec->productRecipeManufacturings as $recipeItem) {
                            $requiredQty = (float) $recipeItem->count * $qty;
                            if ($requiredQty <= 0) {
                                continue;
                            }

                            if (! empty($recipeItem->material_id)) {
                                $matStock = MaterialStock::where('material_id', $recipeItem->material_id)
                                    ->where('branch_id', $branchId)
                                    ->lockForUpdate()
                                    ->first();

                                if ($matStock) {
                                    $current = (float) $matStock->stock;
                                    $deduct = min(max(0.0, $current), $requiredQty);
                                    if ($deduct > 0) {
                                        $matStock->decrement('stock', $deduct);
                                    }
                                }
                            } elseif (! empty($recipeItem->product_recipe_id)) {
                                $recStock = ProductRecipeStock::where('product_recipe_id', $recipeItem->product_recipe_id)
                                    ->where('branch_id', $branchId)
                                    ->lockForUpdate()
                                    ->first();

                                if ($recStock) {
                                    $current = (float) $recStock->stock;
                                    $deduct = min(max(0.0, $current), $requiredQty);
                                    if ($deduct > 0) {
                                        $recStock->decrement('stock', $deduct);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Clear checked out cart items for this uu_id
            OrderCart::where('uu_id', $uuId)->delete();

            return $order;
        });

        $order->load([
            'branch',
            'orderProducts.product',
            'orderProducts.variations.variation',
            'orderProducts.variations.options.option',
            'orderProducts.addons.addon',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الطلب بنجاح من السلة',
            'distance_km' => $nearestResult['distance'],
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * Show single order details.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load([
            'branch',
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
}
