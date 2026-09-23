<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryProductRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_id' => $this->inventory_id,
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe_name' => $this->productRecipe?->name,
            'stock' => (float) $this->stock,
            'actual_stock' => (float) $this->actual_stock,
            'deficit' => (float) ($this->stock - $this->actual_stock),
            'shortage' => (float) ($this->stock - $this->actual_stock),
            'difference' => (float) ($this->actual_stock - $this->stock),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
