<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'stock',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'status' => 'boolean',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productRecipeManufacturings(): HasMany
    {
        return $this->hasMany(ProductRecipeManufacturing::class);
    }

    public function productManufacturings(): HasMany
    {
        return $this->hasMany(ProductManufacturing::class);
    }

    public function manufacturingLists(): HasMany
    {
        return $this->hasMany(ManufacturingList::class);
    }

    public function manufacturingRecipes(): HasMany
    {
        return $this->hasMany(ManufacturingRecipe::class);
    }

    public function wastes(): HasMany
    {
        return $this->hasMany(Waste::class);
    }
}
