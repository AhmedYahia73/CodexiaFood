<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturingRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branchId = $this->manufacturingList?->branch_id ?? $request->input('branch_id');

        return [
            'id' => $this->id,
            'manufacturing_list_id' => $this->manufacturing_list_id,
            'material_id' => $this->material_id,
            'material' => $this->relationLoaded('material') && $this->material ? [
                'id' => $this->material->id,
                'name' => $this->material->name,
                'stock' => $branchId ? (float) $this->material->stockForBranch((int) $branchId) : (float) $this->material->totalStock(),
            ] : null,
            'product_recipe_id' => $this->product_recipe_id,
            'product_recipe' => $this->relationLoaded('productRecipe') && $this->productRecipe ? [
                'id' => $this->productRecipe->id,
                'name' => $this->productRecipe->name,
                'stock' => $branchId ? (float) $this->productRecipe->stockForBranch((int) $branchId) : (float) $this->productRecipe->totalStock(),
            ] : null,
            'count' => (int) $this->count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
