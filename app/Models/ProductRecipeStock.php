<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecipeStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_recipe_id',
        'branch_id',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'product_recipe_id' => 'integer',
            'branch_id' => 'integer',
            'stock' => 'decimal:2',
        ];
    }

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
