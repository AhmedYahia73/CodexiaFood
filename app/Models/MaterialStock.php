<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'branch_id',
        'stock',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'branch_id' => 'integer',
            'stock' => 'decimal:2',
            'unit_id' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
