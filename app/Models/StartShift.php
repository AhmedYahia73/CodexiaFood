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
        'default_total_amount',
        'total_mony',
    ];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'default_total_amount' => 'decimal:2',
            'total_mony' => 'decimal:2',
        ];
    }

    /**
     * Calculate deficit (العجز = default_total_amount - total_mony).
     */
    public function getDeficitAttribute(): float
    {
        $default = (float) ($this->default_total_amount ?? 0);
        $collected = (float) ($this->total_mony ?? 0);

        return round($default - $collected, 2);
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }
}
