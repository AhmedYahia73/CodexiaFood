<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderDet extends Model
{
    use HasFactory;

    protected $table = 'kitchen_order_details';

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
        return $this->belongsTo(KitchenOrderItem::class, 'kitchen_order_item_id');
    }
}
