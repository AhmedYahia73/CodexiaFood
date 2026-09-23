<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'date' => $this->created_at?->format('Y-m-d'),
            'created_at_full' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('inventoryProductRecipes')) {
            $data['items'] = InventoryProductRecipeResource::collection($this->inventoryProductRecipes);
        } elseif ($this->relationLoaded('inventoryMaterials')) {
            $data['items'] = InventoryMaterialResource::collection($this->inventoryMaterials);
        }

        return $data;
    }
}
