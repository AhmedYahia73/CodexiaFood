<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'financial_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
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

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
