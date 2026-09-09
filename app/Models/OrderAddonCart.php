<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddonCart extends Model
{
    use HasFactory;

    protected $table = 'order_addon_carts';

    protected $fillable = [
        'order_cart_id',
        'addon_id',
    ];

    public function orderCart(): BelongsTo
    {
        return $this->belongsTo(OrderCart::class, 'order_cart_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }
}
