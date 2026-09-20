<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
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

        $materialName = $this->material?->name;
        $localizedMaterialName = is_array($materialName)
            ? ($materialName[$locale] ?? $materialName['ar'] ?? $materialName['en'] ?? null)
            : $materialName;

        $recipeName = $this->productRecipe?->name;
        $localizedRecipeName = is_array($recipeName)
            ? ($recipeName[$locale] ?? $recipeName['ar'] ?? $recipeName['en'] ?? null)
            : $recipeName;

        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'material_id' => $this->material_id,
            'material_name' => $localizedMaterialName,
            'material' => new MaterialResource($this->whenLoaded('material')),
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe_name' => $localizedRecipeName,
            'product_recipe' => new ProductRecipeResource($this->whenLoaded('productRecipe')),
            'quantity' => (float) $this->quantity,
            'cost' => (float) $this->cost,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
