<?php

namespace App\Models;

use Database\Factories\BusinessSetupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSetup extends Model
{
    /** @use HasFactory<BusinessSetupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'face',
        'instagram',
        'whats',
        'logo',
        'description',
        'branch_cover',
    ];

    protected $attributes = [
        'branch_cover' => 5.00,
    ];

    protected function casts(): array
    {
        return [
            'branch_cover' => 'decimal:2',
        ];
    }
}
