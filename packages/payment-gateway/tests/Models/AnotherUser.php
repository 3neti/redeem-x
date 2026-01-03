<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Tests\Models;

use LBHurtado\PaymentGateway\Database\Factories\AnotherUserFactory;
use LBHurtado\Merchant\Traits\HasMerchant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\Merchant\Models\Merchant;
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Traits\HasWalletFloat;
use Bavix\Wallet\Traits\CanConfirm;

/**
 * AnotherClass User.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $email
 * @property Merchant   $merchant
 *
 * @method int getKey()
 */
class AnotherUser extends Authenticatable
{
    use HasWalletFloat;
    use HasChannels;
    use HasMerchant;
    use HasFactory;
    use Notifiable;
    use CanConfirm;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile'
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

    public static function newFactory(): AnotherUserFactory
    {
        return AnotherUserFactory::new();
    }
}
