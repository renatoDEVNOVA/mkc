<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Persona;
use Faker\Generator as Faker;

$factory->define(Persona::class, function (Faker $faker) {
    return [
        //
        'apellidos' => $faker->firstName,
        'nombres' => $faker->lastName,
        'genero' => $faker->boolean,
        'tiposPersona' => $faker->numberBetween(1,2),
    ];
});
