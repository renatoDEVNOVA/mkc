<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Campaign;
use Faker\Generator as Faker;

use App\Cliente; 
use App\User; 

$factory->define(Campaign::class, function (Faker $faker) {
    
    $cliente = Cliente::all()->random();
    $user = User::all()->random();

    return [
        //
        'titulo' => $faker->country,
        'fechaInicio' => $faker->date('Y-m-d','now'),
        'fechaFin' => $faker->date('Y-m-d','now'),
        'cliente_id' => $cliente->id,
        'idAgente' => $user->id,
        'tipoPublico' => $faker->numberBetween(1,2),
        'tipoObjetivo' => $faker->numberBetween(1,2),
        'tipoAudiencia' => $faker->numberBetween(1,4),
        'nivelSocioeconomicoA' => $faker->boolean,
        'nivelSocioeconomicoB' => $faker->boolean,
        'nivelSocioeconomicoC' => $faker->boolean,
        'nivelSocioeconomicoD' => $faker->boolean,
        'materialPrensa' => 0,
    ];
});
