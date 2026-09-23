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
        $branchId = $this->branch_id;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'product_id' => $this->product_id,
            'product' => $this->relationLoaded('product') && $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null,
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe' => $this->relationLoaded('productRecipe') && $this->productRecipe ? [
                'id' => $this->productRecipe->id,
                'name' => $this->productRecipe->name,
                'stock' => $branchId ? $this->productRecipe->stockForBranch((int) $branchId) : $this->productRecipe->totalStock(),
            ] : null,
            'count' => (int) $this->count,
            'recipes' => ManufacturingRecipeResource::collection($this->whenLoaded('manufacturingRecipes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
