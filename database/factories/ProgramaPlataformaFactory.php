<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ProgramaPlataforma;
use Faker\Generator as Faker;

use App\Programa; 
use App\MedioPlataforma; 

$factory->define(ProgramaPlataforma::class, function (Faker $faker) {

    $programa = Programa::has('medio.medioPlataformas')->get()->random();
    $medioPlataforma = MedioPlataforma::where('medio_id',$programa->medio_id)->get()->random();

    while (ProgramaPlataforma::where('programa_id',$programa->id)->where('idMedioPlataforma',$medioPlataforma->id)->exists()) {
        # code...
        $programa = Programa::has('medio.medioPlataformas')->get()->random();
        $medioPlataforma = MedioPlataforma::where('medio_id',$programa->medio_id)->get()->random();  
    }

    return [
        //
        'programa_id' => $programa->id,
        'idMedioPlataforma' => $medioPlataforma->id,
        'valor' => $faker->randomNumber(3),
    ];
});
