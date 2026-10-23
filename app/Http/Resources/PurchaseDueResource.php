<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseDueResource extends JsonResource
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
            'purchase_id' => $this->purchase_id,
            'amount' => (float) $this->amount,
            'payment' => (float) ($this->payment ?? 0.00),
            'remaining' => (float) $this->remaining,
            'status' => $this->status,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('purchase', function () {
                return $this->purchase?->supplier ? [
                    'id' => $this->purchase->supplier->id,
                    'name' => $this->purchase->supplier->name,
                    'phone' => $this->purchase->supplier->phone,
                    'balance' => (float) $this->purchase->supplier->balance,
                ] : null;
            }),
            'purchase' => $this->whenLoaded('purchase', function () {
                return [
                    'id' => $this->purchase->id,
                    'supplier_id' => $this->purchase->supplier_id,
                    'branch_id' => $this->purchase->branch_id,
                    'total_cost' => (float) $this->purchase->total_cost,
                    'paid_amount' => (float) $this->purchase->paid_amount,
                    'remaining_amount' => (float) $this->purchase->remaining_amount,
                    'date' => $this->purchase->date,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
