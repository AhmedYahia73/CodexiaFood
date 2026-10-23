<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeShift = $this->relationLoaded('activeStartShift')
            ? $this->activeStartShift
            : ($this->relationLoaded('startShifts')
                ? $this->startShifts->firstWhere('end', null)
                : $this->startShifts()->whereNull('end')->latest('start')->first());

        $hasActiveShift = isset($this->has_active_shift)
            ? (bool) $this->has_active_shift
            : (bool) $activeShift;

        $status = $hasActiveShift ? 'active' : 'in_active';

        $cashierManName = $this->cashierMan?->name ?? $activeShift?->cashierMan?->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'cashier_man_id' => $this->cashier_man_id,
            'cashier_man' => $cashierManName,
            'cashier_man_name' => $cashierManName,
            'status' => $status,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
