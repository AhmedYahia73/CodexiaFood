<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StartShift extends Model
{
    use HasFactory;

    protected $table = 'start_shifts';

    protected $fillable = [
        'start',
        'end',
        'branch_id',
        'cashier_id',
        'cashier_man_id',
        'start_amount',
        'financial_account_id',
        'default_total_amount',
        'total_mony',
        'orders_total',
        'expenses_total',
        'financial_accounts_summary',
    ];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'start_amount' => 'decimal:2',
            'default_total_amount' => 'decimal:2',
            'total_mony' => 'decimal:2',
            'orders_total' => 'decimal:2',
            'expenses_total' => 'decimal:2',
            'financial_accounts_summary' => 'array',
        ];
    }

    /**
     * Calculate deficit (العجز = default_total_amount - total_mony).
     */
    public function getDeficitAttribute(): ?float
    {
        if ($this->total_mony === null) {
            return null;
        }

        $default = (float) ($this->default_total_amount ?? 0);
        $collected = (float) $this->total_mony;

        return round($default - $collected, 2);
    }

    /**
     * Alias accessor for financial_id.
     */
    public function getFinancialIdAttribute(): ?int
    {
        return $this->financial_account_id ? (int) $this->financial_account_id : null;
    }

    /**
     * Alias mutator for financial_id.
     */
    public function setFinancialIdAttribute(mixed $value): void
    {
        $this->attributes['financial_account_id'] = $value;
    }

    /**
     * Alias accessor for total_money.
     */
    public function getTotalMoneyAttribute(): ?float
    {
        return $this->total_mony !== null ? (float) $this->total_mony : null;
    }

    /**
     * Alias mutator for total_money.
     */
    public function setTotalMoneyAttribute(mixed $value): void
    {
        $this->attributes['total_mony'] = $value;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function cashierMan(): BelongsTo
    {
        return $this->belongsTo(CashierMan::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'shift_id');
    }

    /**
     * Compute financial accounts breakdown for this shift.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateFinancialAccountsSummary(?CarbonInterface $endTime = null, string $locale = 'ar'): array
    {
        $endTime = $endTime ?? $this->end ?? now();
        $branchId = $this->branch_id;

        // Fetch active accounts for branch or global
        $accounts = FinancialAccount::query()
            ->where('status', true)
            ->when($branchId, fn ($q) => $q->where(function ($sub) use ($branchId) {
                $sub->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('id', 'asc')
            ->get();

        // Orders during this shift: is_pos = true, status in ('delivered', 'pickup')
        $orders = Order::query()
            ->where('is_pos', true)
            ->whereIn('status', ['delivered', 'pickup'])
            ->where(function ($query) use ($endTime) {
                $query->where('shift_id', $this->id)
                    ->orWhere(function ($q) use ($endTime) {
                        $q->where('cashier_man_id', $this->cashier_man_id)
                            ->where('cashier_id', $this->cashier_id)
                            ->whereBetween('created_at', [$this->start, $endTime]);
                    });
            })
            ->get(['id', 'financial_account_id', 'final_price', 'total']);

        $ordersByAccount = [];
        foreach ($orders as $order) {
            $accId = (int) ($order->financial_account_id ?: 0);
            $amount = (float) ($order->final_price ?: $order->total ?: 0);
            $ordersByAccount[$accId] = round(($ordersByAccount[$accId] ?? 0) + $amount, 2);
        }

        // Expenses during this shift
        $expenses = Expense::query()
            ->where(function ($query) use ($endTime) {
                $query->where('shift_id', $this->id)
                    ->orWhere(function ($q) use ($endTime) {
                        $q->where('cashier_man_id', $this->cashier_man_id)
                            ->whereBetween('created_at', [$this->start, $endTime]);
                    });
            })
            ->get(['id', 'financial_id', 'amount']);

        $expensesByAccount = [];
        foreach ($expenses as $expense) {
            $accId = (int) ($expense->financial_id ?: 0);
            $expensesByAccount[$accId] = round(($expensesByAccount[$accId] ?? 0) + (float) $expense->amount, 2);
        }

        $summary = [];

        foreach ($accounts as $acc) {
            $name = $acc->name;
            if (is_string($name)) {
                $trimmed = trim($name);
                if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $name = $decoded;
                    }
                }
            }
            $localizedName = is_array($name)
                ? ($name[$locale] ?? $name['ar'] ?? $name['en'] ?? '')
                : (string) $name;

            $iconUrl = null;
            if ($acc->icon) {
                $iconUrl = (str_starts_with($acc->icon, 'http://') || str_starts_with($acc->icon, 'https://'))
                    ? $acc->icon
                    : url('storage/'.ltrim($acc->icon, '/'));
            }

            $isStartAccount = ((int) $this->financial_account_id === (int) $acc->id);
            $startAmountForAcc = $isStartAccount ? round((float) ($this->start_amount ?? 0), 2) : 0.00;
            $ordersTotalForAcc = round((float) ($ordersByAccount[$acc->id] ?? 0), 2);
            $expensesTotalForAcc = round((float) ($expensesByAccount[$acc->id] ?? 0), 2);
            $expectedAmountForAcc = round($startAmountForAcc + $ordersTotalForAcc - $expensesTotalForAcc, 2);

            $summary[] = [
                'id' => $acc->id,
                'name' => $localizedName,
                'icon' => $iconUrl,
                'is_start_account' => $isStartAccount,
                'start_amount' => $startAmountForAcc,
                'orders_total' => $ordersTotalForAcc,
                'expenses_total' => $expensesTotalForAcc,
                'expected_amount' => $expectedAmountForAcc,
                'net_amount' => $expectedAmountForAcc,
            ];
        }

        $unassignedOrders = round((float) ($ordersByAccount[0] ?? 0), 2);
        $unassignedExpenses = round((float) ($expensesByAccount[0] ?? 0), 2);
        if ($unassignedOrders > 0 || $unassignedExpenses > 0) {
            $summary[] = [
                'id' => null,
                'name' => $locale === 'en' ? 'Unassigned Account' : 'حساب غير محدد',
                'icon' => null,
                'is_start_account' => false,
                'start_amount' => 0.00,
                'orders_total' => $unassignedOrders,
                'expenses_total' => $unassignedExpenses,
                'expected_amount' => round($unassignedOrders - $unassignedExpenses, 2),
                'net_amount' => round($unassignedOrders - $unassignedExpenses, 2),
            ];
        }

        return $summary;
    }
}
