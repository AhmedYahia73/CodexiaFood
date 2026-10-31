<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $addonName = $this->addon?->name;
        $localizedAddonName = is_array($addonName)
            ? ($addonName[$locale] ?? $addonName['ar'] ?? $addonName['en'] ?? '')
            : $addonName;

        return [
            'id' => $this->id,
            'inventory_id' => $this->inventory_id,
            'addon_id' => $this->addon_id,
            'addon_name' => $localizedAddonName,
            'addon' => new AddonResource($this->whenLoaded('addon')),
            'stock' => (float) $this->stock,
            'actual_stock' => (float) $this->actual_stock,
            'deficit' => (float) ($this->stock - $this->actual_stock),
            'shortage' => (float) ($this->stock - $this->actual_stock),
            'difference' => (float) ($this->actual_stock - $this->stock),
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
