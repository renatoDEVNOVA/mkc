<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Vocero;
use Faker\Generator as Faker;

$factory->define(Vocero::class, function (Faker $faker) {
    return [
        //
        'persona_id' => factory(App\Persona::class),
        'famoso' => $faker->boolean,
    ];
});
