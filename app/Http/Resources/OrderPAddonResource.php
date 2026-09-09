<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPAddonResource extends JsonResource
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

        $addonName = $this->addon?->name;
        $localizedName = is_array($addonName)
            ? ($addonName[$locale] ?? $addonName['ar'] ?? $addonName['en'] ?? null)
            : $addonName;

        return [
            'id' => $this->id,
            'addon_id' => $this->addon_id,
            'order_product_id' => $this->order_product_id,
            'name' => $localizedName,
            'name_translations' => is_array($addonName) ? $addonName : null,
            'price' => (float) $this->price,
            'addon' => $this->whenLoaded('addon'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
