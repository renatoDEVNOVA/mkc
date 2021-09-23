<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use DB;
use Crypt;
use App\Programa;
use App\Persona;
use App\Company;
use App\Medio;
use App\Cliente;
use App\Campaign;
use App\NotaPrensa;
use App\PlanMedio;
use App\Etiqueta;

use Illuminate\Support\Facades\Http;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getCount()
    {

        $data = [
            array("Label" => "Contactos", "Cantidad" => Persona::all()->filter(function($persona){return $persona->isContacto();})->count()),
            array("Label" => "Voceros", "Cantidad" => Persona::all()->filter(function($persona){return $persona->isVocero();})->count()),
            array("Label" => "Compañias", "Cantidad" => Company::all()->count()),
            array("Label" => "Medios", "Cantidad" => Medio::all()->count()),
            array("Label" => "Cientes", "Cantidad" => Cliente::all()->count()),
            array("Label" => "Campañas", "Cantidad" => Campaign::all()->count()),
            array("Label" => "Notas de Prensa", "Cantidad" => NotaPrensa::all()->count()),
            array("Label" => "Planes de Medios", "Cantidad" => PlanMedio::all()->count())
        ];

        return response()->json($data);
    }

    public function tipoDeCambio()
    {
        $response = Http::get('https://api.apis.net.pe/v1/tipo-cambio-sunat');

        return response()->json([
            'ready' => true,
            'response' => $response->json(),
        ]);
    }

}
