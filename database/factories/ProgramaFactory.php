<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Programa;
use Faker\Generator as Faker;

use App\Medio; 

$factory->define(Programa::class, function (Faker $faker) {
    $medio = Medio::all()->random();

    return [
        //
        'nombre' => $faker->jobTitle.'_'.$faker->word,
        'medio_id' => $medio->id,
    ];
});
