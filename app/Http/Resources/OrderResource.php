<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
        $lang = $request->query('lang')
            ?? $request->input('lang')
            ?? $request->header('lang')
            ?? $request->header('Accept-Language')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $shiftName = $this->shift?->name;
        $localizedShiftName = is_array($shiftName)
            ? ($shiftName[$locale] ?? $shiftName['ar'] ?? $shiftName['en'] ?? null)
            : $shiftName;

        $receiptUrl = null;
        if ($this->receipt) {
            if (str_starts_with($this->receipt, 'http://') || str_starts_with($this->receipt, 'https://')) {
                $receiptUrl = $this->receipt;
            } else {
                $receiptUrl = url('storage/'.ltrim($this->receipt, '/'));
            }
        }

        $createdAt = $this->created_at ? Carbon::parse($this->created_at) : null;
        $amPmLower = $createdAt ? strtolower($createdAt->format('a')) : null;
        $amPmUpper = $createdAt ? strtoupper($createdAt->format('A')) : null;

        $hallTable = $this->relationLoaded('hallTable') ? $this->hallTable : null;
        $hall = $hallTable && $hallTable->relationLoaded('hall') ? $hallTable->hall : $hallTable?->hall;
        $hallName = $hall?->name;
        $localizedHallName = is_array($hallName)
            ? ($hallName[$locale] ?? $hallName['ar'] ?? $hallName['en'] ?? null)
            : $hallName;

        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'shift_name' => $localizedShiftName,
            'cashier_id' => $this->cashier_id,
            'cashier_man_id' => $this->cashier_man_id,
            'hall_table_id' => $this->hall_table_id,
            'table_id' => $this->hall_table_id,
            'table_name' => $hallTable?->name,
            'hall_table_name' => $hallTable?->name,
            'hall_name' => $localizedHallName,
            'table' => $hallTable ? [
                'id' => $hallTable->id,
                'code' => $hallTable->code,
                'name' => $hallTable->name,
                'hall_id' => $hallTable->hall_id,
                'hall_name' => $localizedHallName,
            ] : null,
            'captain_order_id' => $this->captain_order_id,
            'captain_order' => new CaptainOrderResource($this->whenLoaded('captainOrder')),
            'financial_account_id' => $this->financial_account_id,
            'financial_id' => $this->financial_account_id,
            'financial_account' => new FinancialAccountResource($this->whenLoaded('financialAccount')),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'receipt' => $receiptUrl,
            'reciept' => $receiptUrl,
            'delivery_id' => $this->delivery_id,
            'delivery' => new DeliveryResource($this->whenLoaded('delivery')),
            'branch_id' => $this->branch_id,
            'module' => $this->module,
            'order_module' => $this->module,
            'date' => $createdAt?->format('Y-m-d'),
            'time' => $createdAt?->format('h:i A'),
            'time_12' => $createdAt?->format('h:i'),
            'am_pm' => $amPmLower,
            'period' => $amPmUpper,
            'order_date' => $createdAt?->format('Y-m-d'),
            'order_time' => $createdAt?->format('h:i A'),
            'order_time_12' => $createdAt?->format('h:i'),
            'order_am_pm' => $amPmLower,
            'order_period' => $amPmUpper,
            'address' => $this->address,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'note' => $this->note,
            'phone' => $this->phone,
            'name' => $this->name,
            'is_pos' => (bool) $this->is_pos,
            'paid' => (bool) $this->paid,
            'status' => $this->status,
            'total' => (float) $this->total,
            'total_tax' => (float) $this->total_tax,
            'total_dinein_tax' => (float) ($this->total_dinein_tax ?? 0),
            'total_discount' => (float) $this->total_discount,
            'final_price' => (float) $this->final_price,
            'branch' => new BranchResource($this->whenLoaded('branch')),
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
