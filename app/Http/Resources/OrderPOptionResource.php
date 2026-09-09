<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPOptionResource extends JsonResource
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

        $optionName = $this->option?->name;
        $localizedName = is_array($optionName)
            ? ($optionName[$locale] ?? $optionName['ar'] ?? $optionName['en'] ?? null)
            : $optionName;

        return [
            'id' => $this->id,
            'order_p_variation_id' => $this->order_p_variation_id,
            'option_id' => $this->option_id,
            'name' => $localizedName,
            'name_translations' => is_array($optionName) ? $optionName : null,
            'price' => (float) $this->price,
            'option' => $this->whenLoaded('option'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
