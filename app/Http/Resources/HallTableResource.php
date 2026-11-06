<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HallTableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $qrUrl = null;
        if ($this->qr) {
            if (str_starts_with($this->qr, 'http://') || str_starts_with($this->qr, 'https://')) {
                $qrUrl = $this->qr;
            } else {
                $qrUrl = url('storage/'.ltrim($this->qr, '/'));
            }
        }

        $lang = $request->query('lang')
            ?? $request->input('lang')
            ?? $request->header('lang')
            ?? $request->header('Accept-Language')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $hall = $this->relationLoaded('hall') ? $this->hall : null;
        $hallName = $hall?->name;
        $localizedHallName = is_array($hallName)
            ? ($hallName[$locale] ?? $hallName['ar'] ?? $hallName['en'] ?? null)
            : $hallName;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'branch_id' => $this->branch_id,
            'branch' => $this->relationLoaded('branch') && $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'hall_id' => $this->hall_id,
            'hall_name' => $localizedHallName,
            'hall' => $hall ? [
                'id' => $hall->id,
                'name' => $hall->name,
                'localized_name' => $localizedHallName,
            ] : null,
            'status' => (bool) $this->status,
            'qr' => $qrUrl,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
