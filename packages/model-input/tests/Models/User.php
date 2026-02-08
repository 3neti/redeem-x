<?php

declare(strict_types=1);

namespace LBHurtado\ModelInput\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LBHurtado\ModelInput\Contracts\InputInterface;
use LBHurtado\ModelInput\Database\Factories\UserFactory;
use LBHurtado\ModelInput\Traits\HasInputs;

/**
 * Class User.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Merchant $merchant
 *
 * @method int getKey()
 */
class User extends Authenticatable implements InputInterface
{
    use HasFactory;
    use HasInputs;
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
        'mobile',
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
