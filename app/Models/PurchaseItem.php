<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_items';

    protected $fillable = [
        'purchase_id',
        'material_id',
        'product_recipe_id',
        'quantity',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }
}
