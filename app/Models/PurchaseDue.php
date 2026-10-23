<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDue extends Model
{
    use HasFactory;

    protected $table = 'purchase_dues';

    protected $fillable = [
        'purchase_id',
        'amount',
        'payment',
        'date',
        'notes',
    ];

    protected $attributes = [
        'payment' => 0.00,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function getRemainingAttribute(): float
    {
        return (float) max(0, round((float) $this->amount - (float) $this->payment, 2));
    }

    public function getIsPaidAttribute(): bool
    {
        return (float) $this->payment >= (float) $this->amount;
    }

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->is_paid && $this->date && $this->date->lt(today());
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_paid) {
            return 'paid';
        }

        if ($this->is_overdue) {
            return 'overdue';
        }

        return 'unpaid';
    }
}
