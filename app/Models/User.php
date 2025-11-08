<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Bavix\Wallet\Interfaces\{Customer, Wallet};
use Bavix\Wallet\Traits\{CanPay, HasWalletFloat};
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\PaymentGateway\Traits\HasMerchant;

class User extends Authenticatable implements Wallet, Customer
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
    use HasPlatformWallets;
    use HasWalletFloat;
    use CanPay;
    use HasVouchers;
    use HasChannels;
    use HasMerchant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Create an API token with specific abilities.
     *
     * @param  string  $name  Token name (e.g., 'mobile-app', 'third-party-integration')
     * @param  array  $abilities  Token abilities/permissions
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createApiToken(string $name, array $abilities = ['*'])
    {
        return $this->createToken($name, $abilities);
    }

    /**
     * Get available API token abilities.
     *
     * @return array
     */
    public static function getApiTokenAbilities(): array
    {
        return [
            'voucher:generate',
            'voucher:list',
            'voucher:view',
            'voucher:cancel',
            'transaction:list',
            'transaction:view',
            'transaction:export',
            'settings:view',
            'settings:update',
            'contact:list',
            'contact:view',
        ];
    }
}
