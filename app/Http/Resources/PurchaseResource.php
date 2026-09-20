<?php

namespace App\Http\Resources;

use App\Models\Material;
use App\Models\ProductRecipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
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

        $materialIds = is_array($this->material_ids) ? $this->material_ids : [];
        $materials = empty($materialIds)
            ? []
            : Material::whereIn('id', $materialIds)->get()->map(function ($item) use ($locale) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name) ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '') : $item->name,
                    'stock' => (int) $item->stock,
                ];
            });

        $recipeIds = is_array($this->product_recipe_id) ? $this->product_recipe_id : ($this->product_recipe_id ? [$this->product_recipe_id] : []);
        $recipes = empty($recipeIds)
            ? []
            : ProductRecipe::whereIn('id', $recipeIds)->get()->map(function ($item) use ($locale) {
                return [
                    'id' => $item->id,
                    'name' => is_array($item->name) ? ($item->name[$locale] ?? $item->name['ar'] ?? $item->name['en'] ?? '') : $item->name,
                    'stock' => (int) $item->stock,
                ];
            });

        return [
            'id' => $this->id,
            'material_ids' => $this->material_ids,
            'product_recipe_id' => $this->product_recipe_id,
            'quantity' => (float) $this->quantity,
            'cost' => (float) $this->cost,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt_url,
            'materials' => $materials,
            'product_recipes' => $recipes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
