<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'balance',
        'status',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
