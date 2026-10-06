<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kitchen_order_id',
        'product_id',
        'addon_id',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function kitchenOrder(): BelongsTo
    {
        return $this->belongsTo(KitchenOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(KitchenOrderDetail::class);
    }

    public function dets(): HasMany
    {
        return $this->hasMany(KitchenOrderDet::class, 'kitchen_order_item_id');
    }
}
