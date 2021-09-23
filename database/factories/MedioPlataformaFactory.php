<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\MedioPlataforma;
use Faker\Generator as Faker;

use App\Medio; 
use App\PlataformaClasificacion; 

$factory->define(MedioPlataforma::class, function (Faker $faker) {

    $medio = Medio::all()->random();
    $plataformaClasificacion = PlataformaClasificacion::all()->random();

    return [
        //
        'medio_id' => $medio->id,
        'idPlataformaClasificacion' => $plataformaClasificacion->id,
        'valor' => $faker->word,
        'alcance' => $faker->randomNumber(5),
    ];
});
