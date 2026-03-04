<?php

namespace LBHurtado\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Contact\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'mobile' => '0917'.str_pad($this->faker->randomNumber(7, true), 7, '0', STR_PAD_LEFT),
            'country' => 'PH',
        ];
    }
}
