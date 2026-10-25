<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'shift_id',
        'cashier_id',
        'cashier_man_id',
        'hall_table_id',
        'captain_order_id',
        'financial_account_id',
        'delivery_id',
        'payment_method_id',
        'receipt',
        'branch_id',
        'module',
        'address',
        'lat',
        'lng',
        'note',
        'phone',
        'name',
        'is_pos',
        'paid',
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
            'paid' => 'boolean',
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
        static::creating(function (Order $order) {
            if (empty($order->code)) {
                $order->code = (string) Str::uuid();
            }

            if (! array_key_exists('paid', $order->attributes) || $order->attributes['paid'] === null) {
                $order->paid = $order->determinePaidStatus();
            }
        });

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

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function captainOrder(): BelongsTo
    {
        return $this->belongsTo(CaptainOrder::class, 'captain_order_id');
    }

    /**
     * Determine whether the order should be considered paid.
     * paid = false if payment method or financial account is_cash true and module = delivery, else true.
     */
    public function determinePaidStatus(): bool
    {
        $isDelivery = ($this->module === 'delivery');

        $isCash = false;
        if ($this->financial_account_id) {
            $isCash = (bool) FinancialAccount::where('id', $this->financial_account_id)->value('is_cash');
        }
        if ($this->payment_method_id) {
            $isCash = $isCash || (bool) PaymentMethod::where('id', $this->payment_method_id)->value('is_cash');
        }

        return ! ($isCash && $isDelivery);
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

    /**
     * Retrieve the model for a bound value by id or UUID code.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->where(function ($query) use ($value) {
            if (is_numeric($value)) {
                $query->where('id', $value)->orWhere('code', $value);
            } else {
                $query->where('code', $value);
            }
        })->first();
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
