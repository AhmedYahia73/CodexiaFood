<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();
        $totalCost = (float) ($this->total_cost ?? $this->cost ?? $items->sum(fn ($i) => (float) $i->quantity * (float) $i->cost));
        $totalQuantity = (float) ($this->total_quantity ?? $this->quantity ?? $items->sum('quantity'));

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt_url,
            'total_cost' => $totalCost,
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'remaining_amount' => (float) ($this->remaining_amount ?? 0),
            'total_quantity' => $totalQuantity,
            'cost' => $totalCost,
            'quantity' => $totalQuantity,
            'notes' => $this->notes,
            'items' => PurchaseItemResource::collection($this->whenLoaded('items', fn () => $this->items, $this->items)),
            'dues' => PurchaseDueResource::collection($this->whenLoaded('dues')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
