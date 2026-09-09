<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPVariation extends Model
{
    use HasFactory;

    protected $table = 'order_p_variations';

    protected $fillable = [
        'order_product_id',
        'variation_id',
    ];

    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderPOption::class, 'order_p_variation_id');
    }
}
