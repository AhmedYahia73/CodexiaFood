<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class CaptainOrder extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory;

    protected $table = 'captain_orders';

    protected $fillable = [
        'name',
        'user_name',
        'password',
        'images',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'images_urls',
        'role',
    ];

    public function getRoleAttribute(): string
    {
        return 'captain_order';
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getImagesUrlsAttribute(): array
    {
        if (empty($this->images) || ! is_array($this->images)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($image) {
            if (! $image) {
                return null;
            }

            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            return url('storage/'.ltrim($image, '/'));
        }, $this->images)));
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'captain_order',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'captain_order_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(OrderCart::class, 'captain_order_id');
    }
}
