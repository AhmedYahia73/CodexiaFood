<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_list_id',
        'financial_id',
        'cashier_id',
        'cashier_man_id',
        'shift_id',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Alias accessor for financial_account_id.
     */
    public function getFinancialAccountIdAttribute(): ?int
    {
        return $this->financial_id ? (int) $this->financial_id : null;
    }

    /**
     * Alias mutator for financial_account_id.
     */
    public function setFinancialAccountIdAttribute(mixed $value): void
    {
        $this->attributes['financial_id'] = $value;
    }

    public function expenseList(): BelongsTo
    {
        return $this->belongsTo(ExpenseList::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function cashierMan(): BelongsTo
    {
        return $this->belongsTo(CashierMan::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(StartShift::class, 'shift_id');
    }
}
