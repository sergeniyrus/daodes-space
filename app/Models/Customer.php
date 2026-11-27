<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens;
    protected $table = 'customers';

    protected $fillable = [
        'name',
        'phone',
        'password',
        'phone_verified_at',
        'user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'customer_organizations',
            'customer_id',
            'organization_id'
        )->withPivot('source')->withTimestamps();
    }

    public function carts(): HasMany
    {
        return $this->hasMany(CustomerCart::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function phoneChallenges(): HasMany
    {
        return $this->hasMany(CustomerPhoneChallenge::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }
}