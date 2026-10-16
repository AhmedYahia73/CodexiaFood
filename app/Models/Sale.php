<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'saler_id',
        'total_cost',
        'paid_amount',
        'remaining_amount',
        'total_quantity',
        'receipt',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'saler_id' => 'integer',
            'total_cost' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'total_quantity' => 'decimal:2',
        ];
    }

    public function saler(): BelongsTo
    {
        return $this->belongsTo(Saler::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function saleItems(): HasMany
    {
        return $this->items();
    }

    public function dues(): HasMany
    {
        return $this->hasMany(SaleDue::class, 'sale_id');
    }

    /**
     * Get full public URL for the receipt image.
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt) {
            return null;
        }

        if (str_starts_with($this->receipt, 'http://') || str_starts_with($this->receipt, 'https://')) {
            return $this->receipt;
        }

        return url('storage/'.ltrim($this->receipt, '/'));
    }
}
