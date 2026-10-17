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
        'financial_account_id',
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

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if ($order->financial_account_id && (float) $order->final_price > 0) {
                FinancialAccount::where('id', $order->financial_account_id)
                    ->increment('balance', (float) $order->final_price);
            }
        });

        static::deleted(function (Order $order) {
            if ($order->financial_account_id && (float) $order->final_price > 0) {
                FinancialAccount::where('id', $order->financial_account_id)
                    ->decrement('balance', (float) $order->final_price);
            }
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged(['final_price', 'financial_account_id'])) {
                $originalAccountId = $order->getOriginal('financial_account_id');
                $originalFinalPrice = (float) $order->getOriginal('final_price');
                $newAccountId = $order->financial_account_id;
                $newFinalPrice = (float) $order->final_price;

                if ($originalAccountId && $originalFinalPrice > 0) {
                    FinancialAccount::where('id', $originalAccountId)->decrement('balance', $originalFinalPrice);
                }

                if ($newAccountId && $newFinalPrice > 0) {
                    FinancialAccount::where('id', $newAccountId)->increment('balance', $newFinalPrice);
                }
            }
        });
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

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
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
        $this->attributes['financial_account_id'] = $value ? (int) $value : null;
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
