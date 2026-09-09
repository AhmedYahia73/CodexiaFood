<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

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
}
