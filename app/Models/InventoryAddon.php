<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'addon_id',
        'stock',
        'actual_stock',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'float',
            'actual_stock' => 'float',
            'unit_id' => 'integer',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
