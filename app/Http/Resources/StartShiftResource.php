<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StartShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        // Localize branch name
        $branchName = $this->branch?->name;
        $localizedBranchName = is_array($branchName)
            ? ($branchName[$locale] ?? $branchName['ar'] ?? $branchName['en'] ?? null)
            : $branchName;

        // Localize cashier desk name
        $cashierName = $this->cashier?->name;
        if (is_string($cashierName)) {
            $decoded = json_decode($cashierName, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $cashierName = $decoded;
            }
        }
        $localizedCashierName = is_array($cashierName)
            ? ($cashierName[$locale] ?? $cashierName['ar'] ?? $cashierName['en'] ?? null)
            : $cashierName;

        $defaultTotal = (float) ($this->default_total_amount ?? 0);
        $totalMony = $this->total_mony !== null ? (float) $this->total_mony : null;
        $deficit = $totalMony !== null ? round($defaultTotal - $totalMony, 2) : null;

        return [
            'id' => $this->id,
            'default_total_amount' => $defaultTotal,
            'total_mony' => $totalMony,
            'deficit' => $deficit,
            'cashier_id' => $this->cashier_id,
            'cashier_name' => $localizedCashierName,
            'branch_id' => $this->branch_id,
            'branch_name' => $localizedBranchName,
            'cashier_man_id' => $this->cashier_man_id,
            'cashier_man_name' => $this->cashierMan?->name,
            'cashier_man' => new CashierManResource($this->whenLoaded('cashierMan')),
            'cashier' => new CashierResource($this->whenLoaded('cashier')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'start' => $this->start?->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
