<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branchId = $request->input('branch_id');
        $branchStock = $branchId ? $this->stockForBranch((int) $branchId) : $this->totalStock();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => (float) $branchStock,
            'total_stock' => (float) $this->totalStock(),
            'status' => (bool) $this->status,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'stocks' => $this->relationLoaded('stocks') ? $this->stocks->map(fn ($s) => [
                'branch_id' => $s->branch_id,
                'branch_name' => $s->branch?->name,
                'stock' => (float) $s->stock,
            ]) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
