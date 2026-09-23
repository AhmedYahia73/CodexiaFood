<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'material_ids',
        'product_recipe_id',
        'quantity',
        'cost',
        'total_cost',
        'total_quantity',
        'receipt',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'material_ids' => 'array',
            'product_recipe_id' => 'array',
            'quantity' => 'decimal:2',
            'cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_quantity' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relationship to PurchaseItems.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->items();
    }

    /**
     * Alias accessor for product_recipe_ids.
     */
    public function getProductRecipeIdsAttribute(): ?array
    {
        return $this->product_recipe_id;
    }

    /**
     * Alias mutator for product_recipe_ids.
     */
    public function setProductRecipeIdsAttribute(mixed $value): void
    {
        $this->attributes['product_recipe_id'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get full public URL for the receipt image.
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt) {
            return null;
        }

        if (str_starts_with($this->receipt, 'http://') || str_starts_with($this->receipt, 'https://')) {
            return $this->receipt;
        }

        return url('storage/'.ltrim($this->receipt, '/'));
    }

    /**
     * Retrieve associated Material models.
     *
     * @return Collection<int, Material>
     */
    public function getMaterials(): Collection
    {
        if (empty($this->material_ids) || ! is_array($this->material_ids)) {
            return new Collection;
        }

        return Material::whereIn('id', $this->material_ids)->get();
    }

    /**
     * Retrieve associated ProductRecipe models.
     *
     * @return Collection<int, ProductRecipe>
     */
    public function getProductRecipes(): Collection
    {
        $ids = $this->product_recipe_id;
        if (empty($ids)) {
            return new Collection;
        }

        $ids = is_array($ids) ? $ids : [$ids];

        return ProductRecipe::whereIn('id', $ids)->get();
    }
}
