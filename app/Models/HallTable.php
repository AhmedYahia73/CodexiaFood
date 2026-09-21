<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HallTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'hall_id',
        'status',
        'qr',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HallTable $hallTable): void {
            if (empty($hallTable->code)) {
                $hallTable->code = (string) Str::uuid();
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->where('id', $value)
            ->orWhere('code', $value)
            ->first();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
