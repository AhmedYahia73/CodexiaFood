<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductManufacturingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branchId = $request->input('branch_id');

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->relationLoaded('product') && $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null,
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe' => $this->relationLoaded('productRecipe') && $this->productRecipe ? [
                'id' => $this->productRecipe->id,
                'name' => $this->productRecipe->name,
                'stock' => $branchId ? (float) $this->productRecipe->stockForBranch((int) $branchId) : (float) $this->productRecipe->totalStock(),
            ] : null,
            'recipes' => ProductRecipeManufacturingResource::collection($this->whenLoaded('productRecipeManufacturings')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
