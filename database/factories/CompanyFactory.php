<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Company;
use Faker\Generator as Faker;

use App\Atributo; 
use App\TipoAtributo; 

$factory->define(Company::class, function (Faker $faker) {

    $documento = Atributo::where('slug', 'document')->first();
    $tipoDocumento = TipoAtributo::where('atributo_id', $documento->id)->get()->random();

    return [
        //
        'nombreComercial' => $faker->company,
        'nroDocumento' => $faker->ean8,
        'idTipoDocumento' => $tipoDocumento->id,
    ];
});
