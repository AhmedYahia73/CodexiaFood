<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_recipe_id',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }

    public function manufacturingRecipes(): HasMany
    {
        return $this->hasMany(ManufacturingRecipe::class);
    }
}
