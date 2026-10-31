<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'image',
        'tax_id',
        'discount_id',
        'kitchen_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(AddonStock::class);
    }

    public function stockForBranch(int $branchId): float
    {
        return (float) ($this->stocks()->where('branch_id', $branchId)->value('stock') ?? 0);
    }

    public function totalStock(): float
    {
        return (float) ($this->stocks()->sum('stock') ?? 0);
    }

    public function inventoryAddons(): HasMany
    {
        return $this->hasMany(InventoryAddon::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
