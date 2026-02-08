<?php

namespace LBHurtado\ModelInput\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelInput\Database\Factories\InputFactory;

class Input extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
    ];

    public static function newFactory(): InputFactory
    {
        return InputFactory::new();
    }
}
