<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'cashier_id',
        'cashier_man_id',
        'hall_table_id',
        'module',
        'address',
        'note',
        'phone',
        'name',
        'is_pos',
        'total',
        'total_tax',
        'total_discount',
        'final_price',
    ];

    protected function casts(): array
    {
        return [
            'is_pos' => 'boolean',
            'total' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'final_price' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function cashierMan(): BelongsTo
    {
        return $this->belongsTo(CashierMan::class);
    }

    public function hallTable(): BelongsTo
    {
        return $this->belongsTo(HallTable::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }
}
