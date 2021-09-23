<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Medio;
use Faker\Generator as Faker;

use App\Company; 

$factory->define(Medio::class, function (Faker $faker) {

    $company = Company::all()->random();

    return [
        //
        'nombre' => $faker->company,
        'filial' => 0,
        'company_id' => $company->id,
    ];
});
