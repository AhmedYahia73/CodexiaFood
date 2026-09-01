<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $iconUrl = null;
        if ($this->icon) {
            if (str_starts_with($this->icon, 'http://') || str_starts_with($this->icon, 'https://')) {
                $iconUrl = $this->icon;
            } else {
                $iconUrl = url('storage/'.ltrim($this->icon, '/'));
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $iconUrl,
            'balance' => (float) $this->balance,
            'status' => (bool) $this->status,
            'branch_id' => $this->branch_id,
            'branch' => $this->relationLoaded('branch') && $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
