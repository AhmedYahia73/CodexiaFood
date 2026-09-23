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
        'branch_id',
        'product_id',
        'product_recipe_id',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'count' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
