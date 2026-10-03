<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSetupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logoUrl = null;
        if ($this->logo) {
            if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
                $logoUrl = $this->logo;
            } else {
                $logoUrl = url('storage/'.ltrim($this->logo, '/'));
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'face' => $this->face,
            'instagram' => $this->instagram,
            'whats' => $this->whats,
            'logo' => $logoUrl,
            'raw_logo' => $this->logo,
            'description' => $this->description,
            'branch_cover' => (float) ($this->branch_cover ?? 5.00),
            'start_day' => $this->start_day ? substr((string) $this->start_day, 0, 5) : '09:00',
            'end_day' => $this->end_day ? substr((string) $this->end_day, 0, 5) : '03:00',
            'is_open' => (bool) $this->isOpen(),
            'is_overnight' => (bool) $this->isOvernight(),
            'working_hours_text' => 'من '.($this->start_day ? substr((string) $this->start_day, 0, 5) : '09:00').' إلى '.($this->end_day ? substr((string) $this->end_day, 0, 5) : '03:00'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
