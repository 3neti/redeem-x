<?php

declare(strict_types=1);

namespace LBHurtado\LocationPreset\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LBHurtado\LocationPreset\Contracts\LocationPresetsInterface;
use LBHurtado\LocationPreset\Database\Factories\UserFactory;
use LBHurtado\LocationPreset\Traits\HasLocationPresets;

class User extends Authenticatable implements LocationPresetsInterface
{
    use HasFactory;
    use HasLocationPresets;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
