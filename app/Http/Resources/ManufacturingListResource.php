<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturingListResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => $this->relationLoaded('product') && $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'stock' => $this->product->stock,
            ] : null,
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe' => $this->relationLoaded('productRecipe') && $this->productRecipe ? [
                'id' => $this->productRecipe->id,
                'name' => $this->productRecipe->name,
                'stock' => $this->productRecipe->stock,
            ] : null,
            'count' => (int) $this->count,
            'recipes' => ManufacturingRecipeResource::collection($this->whenLoaded('manufacturingRecipes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
