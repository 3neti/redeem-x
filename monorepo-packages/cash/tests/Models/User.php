<?php

declare(strict_types=1);

namespace LBHurtado\Cash\Tests\Models;

use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LBHurtado\Cash\Database\Factories\UserFactory;

/**
 * Class User.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 *
 * @method int getKey()
 */
class User extends Authenticatable implements Customer, Wallet
{
    use CanPay;
    use HasFactory;
    use HasWalletFloat;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
