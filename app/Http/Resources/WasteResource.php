<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WasteResource extends JsonResource
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
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe' => new ProductRecipeResource($this->whenLoaded('productRecipe')),
            'material_id' => $this->material_id,
            'material' => new MaterialResource($this->whenLoaded('material')),
            'count' => (int) $this->count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
