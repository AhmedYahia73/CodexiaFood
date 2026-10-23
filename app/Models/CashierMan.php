<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class CashierMan extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory;

    protected $table = 'cashier_men';

    protected $fillable = [
        'name',
        'password',
        'cashier_id',
        'branch_id',
        'shift_id',
    ];

    protected $appends = [
        'role',
        'branch_name',
    ];

    public function getRoleAttribute(): string
    {
        return 'cashier_man';
    }

    public function getBranchNameAttribute(): ?string
    {
        $branch = $this->relationLoaded('branch') ? $this->branch : $this->branch()->first();
        if (! $branch) {
            return null;
        }

        $name = $branch->name;
        if (is_array($name)) {
            $lang = request()?->query('lang')
                ?? request()?->input('lang')
                ?? request()?->header('lang')
                ?? request()?->header('Accept-Language')
                ?? app()->getLocale();
            $locale = str_starts_with(strtolower((string) $lang), 'ar') ? 'ar' : 'en';

            return $name[$locale] ?? $name['ar'] ?? $name['en'] ?? (isset($name[0]) && is_string($name[0]) ? $name[0] : null);
        }

        return is_string($name) ? $name : null;
    }

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Cashier::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function startShifts(): HasMany
    {
        return $this->hasMany(StartShift::class);
    }
}
