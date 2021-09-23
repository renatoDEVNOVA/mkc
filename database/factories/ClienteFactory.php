<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Cliente;
use Faker\Generator as Faker;

use App\Atributo; 
use App\TipoAtributo; 

$factory->define(Cliente::class, function (Faker $faker) {

    $rubro = Atributo::where('slug', 'rubro')->first();
    $tipoRubro = TipoAtributo::where('atributo_id', $rubro->id)->get()->random();

    $documento = Atributo::where('slug', 'document')->first();
    $tipoDocumento = TipoAtributo::where('atributo_id', $documento->id)->get()->random();

    return [
        //
        'nombreComercial' => $faker->name,
        'nroDocumento' => $faker->ean8,
        'idTipoRubro' => $tipoRubro->id,
        'idTipoDocumento' => $tipoDocumento->id,
    ];
});
