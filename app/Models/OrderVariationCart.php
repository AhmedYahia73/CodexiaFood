<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderVariationCart extends Model
{
    use HasFactory;

    protected $table = 'order_variation_carts';

    protected $fillable = [
        'order_cart_id',
        'variation_id',
        'option_ids',
    ];

    protected function casts(): array
    {
        return [
            'option_ids' => 'array',
        ];
    }

    public function orderCart(): BelongsTo
    {
        return $this->belongsTo(OrderCart::class, 'order_cart_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    /**
     * Fetch Option models corresponding to option_ids.
     *
     * @return Collection<int, Option>
     */
    public function getOptions(): Collection
    {
        if (empty($this->option_ids)) {
            return new Collection;
        }

        return Option::whereIn('id', $this->option_ids)->get();
    }
}
