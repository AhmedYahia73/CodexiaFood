<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
        'status',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function inventoryProductRecipes(): HasMany
    {
        return $this->hasMany(InventoryProductRecipe::class);
    }

    public function inventoryMaterials(): HasMany
    {
        return $this->hasMany(InventoryMaterial::class);
    }
}
