<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderCart extends Model
{
    use HasFactory;

    protected $table = 'order_carts';

    protected $fillable = [
        'module',
        'product_id',
        'cashier_id',
        'cashier_man_id',
        'branch_id',
        'hall_table_id',
        'captain_order_id',
        'uu_id',
        'quantity',
        'notes',
        'status',
        'price',
        'is_gift',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'is_gift' => 'boolean',
        ];
    }

    /**
     * Statuses that trigger KitchenOrder cleanup when set.
     */
    public static array $kitchenCleanupStatuses = [
        'done',
        'pickup',
        'return',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variationCarts(): HasMany
    {
        return $this->hasMany(OrderVariationCart::class, 'order_cart_id');
    }

    public function addonCarts(): HasMany
    {
        return $this->hasMany(OrderAddonCart::class, 'order_cart_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function cashierMan(): BelongsTo
    {
        return $this->belongsTo(CashierMan::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hallTable(): BelongsTo
    {
        return $this->belongsTo(HallTable::class);
    }

    public function captainOrder(): BelongsTo
    {
        return $this->belongsTo(CaptainOrder::class, 'captain_order_id');
    }

    public function kitchenOrders(): HasMany
    {
        return $this->hasMany(KitchenOrder::class);
    }

    public function gift(): HasOne
    {
        return $this->hasOne(OrderCart::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrderCart::class, 'parent_id');
    }
}
