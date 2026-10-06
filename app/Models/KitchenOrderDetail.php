<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'kitchen_order_item_id',
        'option_ids',
    ];

    protected function casts(): array
    {
        return [
            'option_ids' => 'array',
        ];
    }

    public function kitchenOrderItem(): BelongsTo
    {
        return $this->belongsTo(KitchenOrderItem::class);
    }
}
