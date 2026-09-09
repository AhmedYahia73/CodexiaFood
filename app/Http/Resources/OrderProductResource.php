<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? $request->query('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $productName = $this->product?->name;
        $localizedName = is_array($productName)
            ? ($productName[$locale] ?? $productName['ar'] ?? $productName['en'] ?? null)
            : $productName;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'name' => $localizedName,
            'name_translations' => is_array($productName) ? $productName : null,
            'price' => (float) $this->price,
            'note' => $this->note,
            'product' => new ProductResource($this->whenLoaded('product')),
            'variations' => OrderPVariationResource::collection($this->whenLoaded('variations')),
            'addons' => OrderPAddonResource::collection($this->whenLoaded('addons')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
