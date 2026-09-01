<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'product_recipe_id',
        'count',
        'manufacturing_list_id',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function productRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductRecipe::class);
    }

    public function manufacturingList(): BelongsTo
    {
        return $this->belongsTo(ManufacturingList::class);
    }
}
