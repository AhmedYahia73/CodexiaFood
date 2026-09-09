<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? $request->query('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $shiftName = $this->shift?->name;
        $localizedShiftName = is_array($shiftName)
            ? ($shiftName[$locale] ?? $shiftName['ar'] ?? $shiftName['en'] ?? null)
            : $shiftName;

        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'shift_name' => $localizedShiftName,
            'cashier_id' => $this->cashier_id,
            'cashier_man_id' => $this->cashier_man_id,
            'hall_table_id' => $this->hall_table_id,
            'module' => $this->module,
            'address' => $this->address,
            'note' => $this->note,
            'phone' => $this->phone,
            'name' => $this->name,
            'is_pos' => (bool) $this->is_pos,
            'total' => (float) $this->total,
            'total_tax' => (float) $this->total_tax,
            'total_discount' => (float) $this->total_discount,
            'final_price' => (float) $this->final_price,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'cashier' => new CashierResource($this->whenLoaded('cashier')),
            'cashier_man' => new CashierManResource($this->whenLoaded('cashierMan')),
            'hall_table' => new HallTableResource($this->whenLoaded('hallTable')),
            'products' => OrderProductResource::collection($this->whenLoaded('orderProducts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
