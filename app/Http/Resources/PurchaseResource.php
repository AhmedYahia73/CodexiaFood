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
        $totalCost = (float) ($this->total_cost ?? $this->cost ?? $items->sum('cost'));
        $totalQuantity = (float) ($this->total_quantity ?? $this->quantity ?? $items->sum('quantity'));

        return [
            'id' => $this->id,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt_url,
            'total_cost' => $totalCost,
            'total_quantity' => $totalQuantity,
            'cost' => $totalCost,
            'quantity' => $totalQuantity,
            'notes' => $this->notes,
            'items' => PurchaseItemResource::collection($this->whenLoaded('items', fn () => $this->items, $this->items)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
