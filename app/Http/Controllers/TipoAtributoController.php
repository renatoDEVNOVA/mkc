<?php

namespace App\Http\Controllers;

use App\TipoAtributo;
use Illuminate\Http\Request;

use App\Atributo;
use App\ClienteEmail;
use App\PersonaEmail;
use App\MedioEmail;
use App\ClienteTelefono;
use App\PersonaTelefono;
use App\MedioTelefono;
use App\PersonaDireccion;
use App\MedioDireccion;
use App\PersonaRed;
use App\MedioRed;
use App\PersonaHorario;
use App\Company;
use App\Cliente;
use App\Bitacora;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class TipoAtributoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tipoAtributos = TipoAtributo::with('atributo')->get();

        return response()->json([
            'ready' => true,
            'tipoAtributos' => $tipoAtributos,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'name.unique' => 'Ya se encuentra registrado el tipo deseado con el mismo nombre.',
                'atributo_id.required' => 'El atributo es obligatorio.',
                'atributo_id.exists' => 'Seleccione un atributo valido.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    Rule::unique('tipo_atributos')->where(function ($query) use ($request){
                        return $query->where('atributo_id', $request->atributo_id)->whereNull('deleted_at');
                    }),
                ],
                'atributo_id' => ['required','exists:atributos,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $atributo = Atributo::find($request->atributo_id);

            $data = array(
                'name' => $request->name,
                'atributo_id' => $request->atributo_id,
            );

            $tipoAtributo = TipoAtributo::create($data);

            if (!$tipoAtributo->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de '.$atributo->name.' no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de '.$atributo->name.' se ha creado correctamente',
                'tipoAtributo' => $tipoAtributo,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\TipoAtributo  $tipoAtributo
     * @return \Illuminate\Http\Response
     */
    public function show(TipoAtributo $tipoAtributo)
    {
        //
        if(is_null($tipoAtributo)){
            return response()->json([
                'ready' => false,
                'message' => 'El tipo de atributo no se pudo encontrar',
            ], 404);
        }else{

            $tipoAtributo->atributo;

            return response()->json([
                'ready' => true,
                'tipoAtributo' => $tipoAtributo,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TipoAtributo  $tipoAtributo
     * @return \Illuminate\Http\Response
     */
    public function edit(TipoAtributo $tipoAtributo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TipoAtributo  $tipoAtributo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TipoAtributo $tipoAtributo)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'name.unique' => 'Ya se encuentra registrado el tipo deseado con el mismo nombre.',
                //'atributo_id.required' => 'El atributo es obligatorio.',
                //'atributo_id.exists' => 'Seleccione un atributo valido.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    Rule::unique('tipo_atributos')->ignore($tipoAtributo->id)->where(function ($query) use ($tipoAtributo){
                        return $query->where('atributo_id', $tipoAtributo->atributo_id)->whereNull('deleted_at');
                    }),
                ],
                //'atributo_id' => ['required','exists:atributos,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            //$atributo = Atributo::find($request->atributo_id);
            $atributo = Atributo::find($tipoAtributo->atributo_id);

            $tipoAtributo->name = $request->name;
            //$tipoAtributo->atributo_id = $request->atributo_id;
            if (!$tipoAtributo->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de '.$atributo->name.' no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de '.$atributo->name.' se ha actualizado correctamente',
                'tipoAtributo' => $tipoAtributo,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\TipoAtributo  $tipoAtributo
     * @return \Illuminate\Http\Response
     */
    public function destroy(TipoAtributo $tipoAtributo)
    {
        //
        try {
            DB::beginTransaction();

            $atributo = Atributo::find($tipoAtributo->atributo_id);

            switch ($atributo->slug) {
                case 'email':
                    # code...
                    $existsClienteEmail = ClienteEmail::where('idTipoEmail', $tipoAtributo->id)->exists();

                    if($existsClienteEmail){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes clientes.',
                        ], 400);
                    }

                    $existsPersonaEmail = PersonaEmail::where('idTipoEmail', $tipoAtributo->id)->exists();

                    if($existsPersonaEmail){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes personas.',
                        ], 400);
                    }

                    $existsMedioEmail = MedioEmail::where('idTipoEmail', $tipoAtributo->id)->exists();

                    if($existsMedioEmail){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes medios.',
                        ], 400);
                    }
                    break;

                case 'telephone':
                    # code...
                    $existsClienteTelefono = ClienteTelefono::where('idTipoTelefono', $tipoAtributo->id)->exists();

                    if($existsClienteTelefono){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes clientes.',
                        ], 400);
                    }

                    $existsPersonaTelefono = PersonaTelefono::where('idTipoTelefono', $tipoAtributo->id)->exists();

                    if($existsPersonaTelefono){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes personas.',
                        ], 400);
                    }

                    $existsMedioTelefono = MedioTelefono::where('idTipoTelefono', $tipoAtributo->id)->exists();

                    if($existsMedioTelefono){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes medios.',
                        ], 400);
                    }
                    break;

                case 'address':
                    # code...
                    $existsPersonaDireccion = PersonaDireccion::where('idTipoDireccion', $tipoAtributo->id)->exists();

                    if($existsPersonaDireccion){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes personas.',
                        ], 400);
                    }

                    $existsMedioDireccion = MedioDireccion::where('idTipoDireccion', $tipoAtributo->id)->exists();

                    if($existsMedioDireccion){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes medios.',
                        ], 400);
                    }
                    break;

                case 'social-media':
                    # code...
                    $existsPersonaRed = PersonaRed::where('idTipoRed', $tipoAtributo->id)->exists();

                    if($existsPersonaRed){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes personas.',
                        ], 400);
                    }

                    $existsMedioRed = MedioRed::where('idTipoRed', $tipoAtributo->id)->exists();

                    if($existsMedioRed){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes medios.',
                        ], 400);
                    }
                    break;

                case 'horario':
                    # code...
                    $existsPersonaHorario = PersonaHorario::where('idTipoHorario', $tipoAtributo->id)->exists();

                    if($existsPersonaHorario){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes personas.',
                        ], 400);
                    }
                    break;

                case 'document':
                    # code...
                    $existsCompany = Company::where('idTipoDocumento', $tipoAtributo->id)->exists();

                    if($existsCompany){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes compañias.',
                        ], 400);
                    }

                    $existsCliente = Cliente::where('idTipoDocumento', $tipoAtributo->id)->exists();

                    if($existsCliente){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes clientes.',
                        ], 400);
                    }
                    break;

                case 'comunicacion':
                    # code...
                    $existsBitacora = Bitacora::where('idTipoComunicacion', $tipoAtributo->id)->exists();

                    if($existsBitacora){
                        return response()->json([
                            'ready' => false,
                            'message' => 'Imposible de eliminar. El tipo de '.$atributo->name.' se encuentra relacionado con diferentes bitacoras.',
                        ], 400);
                    }
                    break;
                
                default:
                    # code...
                    break;
            }

            if (!$tipoAtributo->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de '.$atributo->name.' no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de '.$atributo->name.' se ha eliminado correctamente',
                'tipoAtributo' => $tipoAtributo,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListBySlug($slug)
    {
        $tipoAtributos = TipoAtributo::with('atributo')->get()->filter(function ($tipoAtributo) use ($slug){
            return $tipoAtributo->atributo->slug == $slug;
        });

        return response()->json([
            'ready' => true,
            'tipoAtributos' => $tipoAtributos->values(),
        ]);
    }
}
