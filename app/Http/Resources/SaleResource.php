<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();
        $totalCost = (float) ($this->total_cost ?? $items->sum(fn ($i) => (float) $i->quantity * (float) $i->cost));
        $totalQuantity = (float) ($this->total_quantity ?? $items->sum('quantity'));

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'saler_id' => $this->saler_id,
            'saler' => $this->relationLoaded('saler') && $this->saler ? [
                'id' => $this->saler->id,
                'name' => $this->saler->name,
                'phone' => $this->saler->phone,
                'balance' => (float) $this->saler->balance,
            ] : null,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt_url,
            'total_cost' => $totalCost,
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'remaining_amount' => (float) ($this->remaining_amount ?? 0),
            'total_quantity' => $totalQuantity,
            'notes' => $this->notes,
            'items' => SaleItemResource::collection($this->whenLoaded('items', fn () => $this->items, $this->items)),
            'dues' => SaleDueResource::collection($this->whenLoaded('dues')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
