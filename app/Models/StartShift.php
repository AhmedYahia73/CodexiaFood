<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartShift extends Model
{
    use HasFactory;

    protected $table = 'start_shifts';

    protected $fillable = [
        'start',
        'end',
        'branch_id',
        'cashier_id',
        'cashier_man_id',
    ];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class);
    }

    public function cashierMan(): BelongsTo
    {
        return $this->belongsTo(CashierMan::class);
    }
}
