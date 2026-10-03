<?php

namespace App\Models;

use App\Services\RestaurantWorkingHoursService;
use Carbon\Carbon;
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
        'start_day',
        'end_day',
    ];

    protected $attributes = [
        'branch_cover' => 5.00,
        'start_day' => '09:00:00',
        'end_day' => '03:00:00',
    ];

    protected function casts(): array
    {
        return [
            'branch_cover' => 'decimal:2',
        ];
    }

    public function isOpen(?Carbon $now = null): bool
    {
        return app(RestaurantWorkingHoursService::class)->setBusinessSetup($this)->isOpen($now);
    }

    public function isOvernight(): bool
    {
        return app(RestaurantWorkingHoursService::class)->setBusinessSetup($this)->isOvernight();
    }
}
