<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductManufacturing extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_recipe_id',
        'product_id',
    ];

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productRecipeManufacturings(): HasMany
    {
        return $this->hasMany(ProductRecipeManufacturing::class, 'product_manufact_id');
    }
}
