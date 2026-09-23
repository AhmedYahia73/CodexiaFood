<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Branch extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_name',
        'address',
        'location',
        'watts',
        'facebook',
        'status',
        'password',
    ];

    protected $appends = [
        'role',
    ];

    public function getRoleAttribute()
    {
        return 'branch';
    }

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'location' => 'array',
            'status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
        ];
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function cashiers(): HasMany
    {
        return $this->hasMany(Cashier::class);
    }

    public function cashierMen(): HasMany
    {
        return $this->hasMany(CashierMan::class);
    }

    public function halls(): HasMany
    {
        return $this->hasMany(Hall::class);
    }

    public function hallTables(): HasMany
    {
        return $this->hasMany(HallTable::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function materialStocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function productRecipeStocks(): HasMany
    {
        return $this->hasMany(ProductRecipeStock::class);
    }

    public function manufacturingLists(): HasMany
    {
        return $this->hasMany(ManufacturingList::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function wastes(): HasMany
    {
        return $this->hasMany(Waste::class);
    }
}
