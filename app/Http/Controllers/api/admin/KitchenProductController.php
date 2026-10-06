<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenProductController extends Controller
{
    /**
     * List all products assigned to a specific kitchen.
     * Can receive kitchen via route model or kitchen_id via query/body.
     */
    public function kitchenProducts(Request $request, Kitchen|int|string|null $kitchen = null): JsonResponse
    {
        $kitchenId = $kitchen instanceof Kitchen
            ? $kitchen->id
            : ($kitchen ?? $request->query('kitchen_id') ?? $request->input('kitchen_id'));

        if (! $kitchenId) {
            return response()->json([
                'status' => false,
                'message' => 'kitchen_id is required.',
            ], 422);
        }

        $lang = $request->query('lang') ?? $request->header('lang') ?? 'ar';

        $products = Product::where('kitchen_id', (int) $kitchenId)
            ->select('id', 'name', 'kitchen_id', 'price')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => is_array($product->name) ? ($product->name[$lang] ?? $product->name['ar'] ?? $product->name['en'] ?? '') : $product->name,
                'name_ar' => is_array($product->name) ? ($product->name['ar'] ?? '') : $product->name,
                'name_en' => is_array($product->name) ? ($product->name['en'] ?? '') : $product->name,
                'price' => $product->price,
            ]);

        return response()->json([
            'status' => true,
            'data' => $products,
        ]);
    }

    /**
     * List all products where kitchen_id is null, with localized names (en, ar).
     */
    public function unassigned(Request $request): JsonResponse
    {
        $lang = $request->query('lang') ?? $request->header('lang') ?? 'ar';

        $products = Product::whereNull('kitchen_id')
            ->select('id', 'name', 'price')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => is_array($product->name) ? ($product->name[$lang] ?? $product->name['ar'] ?? $product->name['en'] ?? '') : $product->name,
                'name_ar' => is_array($product->name) ? ($product->name['ar'] ?? '') : $product->name,
                'name_en' => is_array($product->name) ? ($product->name['en'] ?? '') : $product->name,
                'price' => $product->price,
            ]);

        return response()->json([
            'status' => true,
            'data' => $products,
        ]);
    }

    /**
     * Assign a list of products to a kitchen.
     *
     * @bodyParam kitchen_id int required The kitchen ID. Example: 1
     * @bodyParam product_ids int[] required The product IDs to assign. Example: [1,2,3]
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kitchen_id' => 'required|exists:kitchens,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $productIds = $validated['product_ids'];

        Product::whereIn('id', $productIds)
            ->update(['kitchen_id' => $validated['kitchen_id']]);

        return response()->json([
            'status' => true,
            'message' => 'Products assigned to kitchen successfully.',
            'count' => count($productIds),
        ]);
    }
}
