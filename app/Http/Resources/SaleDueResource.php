<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleDueResource extends JsonResource
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
            'sale_id' => $this->sale_id,
            'amount' => (float) $this->amount,
            'payment' => (float) ($this->payment ?? 0.00),
            'remaining' => (float) $this->remaining,
            'status' => $this->status,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'notes' => $this->notes,
            'saler' => $this->whenLoaded('sale', function () {
                return $this->sale?->saler ? [
                    'id' => $this->sale->saler->id,
                    'name' => $this->sale->saler->name,
                    'phone' => $this->sale->saler->phone,
                    'balance' => (float) $this->sale->saler->balance,
                ] : null;
            }),
            'sale' => $this->whenLoaded('sale', function () {
                return [
                    'id' => $this->sale->id,
                    'saler_id' => $this->sale->saler_id,
                    'branch_id' => $this->sale->branch_id,
                    'total_cost' => (float) $this->sale->total_cost,
                    'paid_amount' => (float) $this->sale->paid_amount,
                    'remaining_amount' => (float) $this->sale->remaining_amount,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
