<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @mixin Builder
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'password',
        'email',
        'phone_number',
        'wallet_balance',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'should_change_password'
    ];

    protected $hidden = [
        'password',
        'otp',
        'otp_expires_at',
        'should_change_password'
    ];

    public function addresses(): User|HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function carts(): User|hasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function history(): User|HasMany
    {
        return $this->hasMany(History::class);
    }
}
