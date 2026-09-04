<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stock',
        'status',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'stock' => 'integer',
            'status' => 'boolean',
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
