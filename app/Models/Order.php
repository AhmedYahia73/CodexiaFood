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
        'branch_id',
        'module',
        'address',
        'lat',
        'lng',
        'note',
        'phone',
        'name',
        'is_pos',
        'status',
        'total',
        'total_tax',
        'total_discount',
        'final_price',
    ];

    protected function casts(): array
    {
        return [
            'is_pos' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'total' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'final_price' => 'decimal:2',
        ];
    }

    /**
     * Statuses that trigger KitchenOrder cleanup when set.
     */
    public static array $kitchenCleanupStatuses = [
        'done',
        'pickup',
        'out_of_delivery',
        'delivered',
        'return',
        'failed_to_delivered',
        'faild_to_delivered',
    ];

    /**
     * Allowed status transitions per module for admin.
     */
    public static array $allowedStatusTransitions = [
        'delivery' => ['done', 'out_of_delivery', 'delivered', 'return', 'failed_to_delivered', 'faild_to_delivered'],
        'takeaway' => ['done', 'pickup', 'return'],
        'dinein' => ['return'],
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function kitchenOrders(): HasMany
    {
        return $this->hasMany(KitchenOrder::class);
    }
}
