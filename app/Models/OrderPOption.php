<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPOption extends Model
{
    use HasFactory;

    protected $table = 'order_p_options';

    protected $fillable = [
        'order_p_variation_id',
        'option_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function orderPVariation(): BelongsTo
    {
        return $this->belongsTo(OrderPVariation::class, 'order_p_variation_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }
}
