<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPVariationResource extends JsonResource
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

        $variationName = $this->variation?->name;
        $localizedName = is_array($variationName)
            ? ($variationName[$locale] ?? $variationName['ar'] ?? $variationName['en'] ?? null)
            : $variationName;

        return [
            'id' => $this->id,
            'order_product_id' => $this->order_product_id,
            'variation_id' => $this->variation_id,
            'name' => $localizedName,
            'name_translations' => is_array($variationName) ? $variationName : null,
            'variation' => $this->whenLoaded('variation'),
            'options' => OrderPOptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
