<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'branch_id',
        'is_tomorrow',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_tomorrow' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift): void {
            if ($shift->start_time && $shift->end_time) {
                $shift->is_tomorrow = $shift->end_time < $shift->start_time;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashierMen(): HasMany
    {
        return $this->hasMany(CashierMan::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
