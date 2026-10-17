<?php

namespace App\Models;

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
}
