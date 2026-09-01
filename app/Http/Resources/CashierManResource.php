<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierManResource extends JsonResource
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
            'name' => $this->name,
            'cashier_id' => $this->cashier_id,
            'branch_id' => $this->branch_id,
            'role' => $this->role,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'cashier' => new CashierResource($this->whenLoaded('cashier')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
