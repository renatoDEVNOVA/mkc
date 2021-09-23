<?php

namespace App\Http\Controllers;

use App\Contacto;
use Illuminate\Http\Request;

use Validator;
use DB;
use App\Persona;
use App\PersonaEmail;
use App\PersonaTelefono;
use App\PersonaDireccion;
use App\PersonaRed;
use Illuminate\Validation\Rule;

class ContactoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $contactos = Contacto::with(
            'persona',
            'persona.tipoProfesion',
            'persona.emails.tipoEmail',
            'persona.telefonos.tipoTelefono',
            'persona.direccions.tipoDireccion',
            'persona.reds.tipoRed',
            'programaContactos.programa.medio',
        )->get();

        return response()->json([
            'ready' => true,
            'contactos' => $contactos,
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
                'apellidos.required' => 'Apellidos es obligatorio.',
                'nombres.required' => 'Nombres es obligatorio.',
                'genero.required' => 'El Genero es obligatorio.',
                'genero.boolean' => 'Seleccione un Genero valido.',
            ];

            $validator = Validator::make($request->all(), [
                'apellidos' => ['required'],
                'nombres' => ['required'],
                'genero' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'apellidos' => $request->apellidos,
                'nombres' => $request->nombres,
                'genero' => $request->genero,
            );

            $persona = Persona::create($data);

            if (!$persona->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $persona->apodo = isset($request->apodo) ? $request->apodo : null;
            $persona->fechaNacimiento = isset($request->fechaNacimiento) ? $request->fechaNacimiento : null;
            $persona->idTipoProfesion = isset($request->idTipoProfesion) ? $request->idTipoProfesion : null;
            $persona->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no se ha creado',
                ], 500);
            }

            if (isset($request->emails)) {

                $persona_emails = json_decode(json_encode($request->emails));

                foreach ($persona_emails as $persona_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$persona_email->email.' ya se encuentra registrado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $persona_email->email), [
                        'email' => ['unique:persona_emails,email,NULL,id,deleted_at,NULL', 'email'],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'persona_email' => $persona_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'persona_id' => $persona->id,
                        'email' => $persona_email->email,
                        'idTipoEmail' => $persona_email->idTipoEmail,
                    );

                    $personaEmail = PersonaEmail::create($dataEmail);
                    if (!$personaEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->telefonos)) {

                $persona_telefonos = json_decode(json_encode($request->telefonos));

                foreach ($persona_telefonos as $persona_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$persona_telefono->telefono.' ya se encuentra registrado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $persona_telefono->telefono), [
                        'telefono' => ['unique:persona_telefonos,telefono,NULL,id,deleted_at,NULL'],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'persona_telefono' => $persona_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'persona_id' => $persona->id,
                        'telefono' => $persona_telefono->telefono,
                        'idTipoTelefono' => $persona_telefono->idTipoTelefono,
                    );

                    $personaTelefono = PersonaTelefono::create($dataTelefono);
                    if (!$personaTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->direccions)) {

                $persona_direccions = json_decode(json_encode($request->direccions));

                foreach ($persona_direccions as $persona_direccion) {
                    # code...
                    $dataDireccion = array(
                        'persona_id' => $persona->id,
                        'direccion' => $persona_direccion->direccion,
                        'idTipoDireccion' => $persona_direccion->idTipoDireccion,
                    );

                    $personaDireccion = PersonaDireccion::create($dataDireccion);
                    if (!$personaDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->reds)) {

                $persona_reds = json_decode(json_encode($request->reds));

                foreach ($persona_reds as $persona_red) {
                    # code...

                    $messagesRed = [
                        'red.unique' => 'La red social '.$persona_red->red.' ya se encuentra registrada.',
                    ];
    
                    $validatorRed = Validator::make(array('red' => $persona_red->red), [
                        'red' => [
                            Rule::unique('persona_reds')->where(function ($query) use ($persona_red){
                                return $query->where('idTipoRed', $persona_red->idTipoRed)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesRed);

                    if ($validatorRed->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorRed->errors(),
                            'persona_red' => $persona_red,
                        ], 400);
                    }

                    $dataRed = array(
                        'persona_id' => $persona->id,
                        'red' => $persona_red->red,
                        'idTipoRed' => $persona_red->idTipoRed,
                    );

                    $personaRed = PersonaRed::create($dataRed);
                    if (!$personaRed->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha creado',
                        ], 500);
                    }
                }
            }

            // Datos Obligatorios
            $data = array(
                'persona_id' => $persona->id,
            );

            $contacto = Contacto::create($data);

            if (!$contacto->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El contacto se ha creado correctamente',
                'contacto' => $contacto,
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
     * @param  \App\Contacto  $contacto
     * @return \Illuminate\Http\Response
     */
    public function show(Contacto $contacto)
    {
        if(is_null($contacto)){
            return response()->json([
                'ready' => false,
                'message' => 'Contacto no encontrado',
            ], 404);
        }else{

            $contacto->persona->tipoProfesion;
            $contacto->persona->emails = $contacto->persona->emails()->get()->map(function($email){
                $email->tipoEmail->atributo;
                return $email;
            });;
            $contacto->persona->telefonos = $contacto->persona->telefonos()->get()->map(function($telefono){
                $telefono->tipoTelefono->atributo;
                return $telefono;
            });;
            $contacto->persona->direccions = $contacto->persona->direccions()->get()->map(function($direccion){
                $direccion->tipoDireccion->atributo;
                return $direccion;
            });;
            $contacto->persona->reds = $contacto->persona->reds()->get()->map(function($red){
                $red->tipoRed->atributo;
                return $red;
            });;

            return response()->json([
                'ready' => true,
                'contacto' => $contacto,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contacto  $contacto
     * @return \Illuminate\Http\Response
     */
    public function edit(Contacto $contacto)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contacto  $contacto
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contacto $contacto)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'apellidos.required' => 'Apellidos es obligatorio.',
                'nombres.required' => 'Nombres es obligatorio.',
                'genero.required' => 'El Genero es obligatorio.',
                'genero.boolean' => 'Seleccione un Genero valido.',
            ];

            $validator = Validator::make($request->all(), [
                'apellidos' => ['required'],
                'nombres' => ['required'],
                'genero' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $persona = Persona::find($contacto->persona_id);

            // Datos Obligatorios
            $persona->apellidos = $request->apellidos;
            $persona->nombres = $request->nombres;
            $persona->genero = $request->genero;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $persona->apodo = isset($request->apodo) ? $request->apodo : null;
            $persona->fechaNacimiento = isset($request->fechaNacimiento) ? $request->fechaNacimiento : null;
            $persona->idTipoProfesion = isset($request->idTipoProfesion) ? $request->idTipoProfesion : null;
            $persona->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no se ha actualizado',
                ], 500);
            }


            if (isset($request->emails)) {

                // Eliminamos los registros actuales
                PersonaEmail::where('persona_id', $persona->id)->delete();

                $persona_emails = json_decode(json_encode($request->emails));

                foreach ($persona_emails as $persona_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$persona_email->email.' ya se encuentra registrado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $persona_email->email), [
                        'email' => ['unique:persona_emails,email,NULL,id,deleted_at,NULL', 'email'],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'persona_email' => $persona_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'persona_id' => $persona->id,
                        'email' => $persona_email->email,
                        'idTipoEmail' => $persona_email->idTipoEmail,
                    );

                    $personaEmail = PersonaEmail::create($dataEmail);
                    if (!$personaEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->telefonos)) {

                // Eliminamos los registros actuales
                PersonaTelefono::where('persona_id', $persona->id)->delete();

                $persona_telefonos = json_decode(json_encode($request->telefonos));

                foreach ($persona_telefonos as $persona_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$persona_telefono->telefono.' ya se encuentra registrado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $persona_telefono->telefono), [
                        'telefono' => ['unique:persona_telefonos,telefono,NULL,id,deleted_at,NULL'],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'persona_telefono' => $persona_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'persona_id' => $persona->id,
                        'telefono' => $persona_telefono->telefono,
                        'idTipoTelefono' => $persona_telefono->idTipoTelefono,
                    );

                    $personaTelefono = PersonaTelefono::create($dataTelefono);
                    if (!$personaTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->direccions)) {

                // Eliminamos los registros actuales
                PersonaDireccion::where('persona_id', $persona->id)->delete();

                $persona_direccions = json_decode(json_encode($request->direccions));

                foreach ($persona_direccions as $persona_direccion) {
                    # code...
                    $dataDireccion = array(
                        'persona_id' => $persona->id,
                        'direccion' => $persona_direccion->direccion,
                        'idTipoDireccion' => $persona_direccion->idTipoDireccion,
                    );

                    $personaDireccion = PersonaDireccion::create($dataDireccion);
                    if (!$personaDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->reds)) {

                // Eliminamos los registros actuales
                PersonaRed::where('persona_id', $persona->id)->delete();

                $persona_reds = json_decode(json_encode($request->reds));

                foreach ($persona_reds as $persona_red) {
                    # code...

                    $messagesRed = [
                        'red.unique' => 'La red social '.$persona_red->red.' ya se encuentra registrada.',
                    ];
    
                    $validatorRed = Validator::make(array('red' => $persona_red->red), [
                        'red' => [
                            Rule::unique('persona_reds')->where(function ($query) use ($persona_red){
                                return $query->where('idTipoRed', $persona_red->idTipoRed)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesRed);

                    if ($validatorRed->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorRed->errors(),
                            'persona_red' => $persona_red,
                        ], 400);
                    }

                    $dataRed = array(
                        'persona_id' => $persona->id,
                        'red' => $persona_red->red,
                        'idTipoRed' => $persona_red->idTipoRed,
                    );

                    $personaRed = PersonaRed::create($dataRed);
                    if (!$personaRed->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El contacto no se ha actualizado',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El contacto se ha actualizado correctamente',
                'contacto' => $contacto,
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
     * @param  \App\Contacto  $contacto
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contacto $contacto)
    {
        //
        $persona = Persona::find($contacto->persona_id); 

        PersonaEmail::where('persona_id', $persona->id)->delete();
        PersonaTelefono::where('persona_id', $persona->id)->delete();
        PersonaDireccion::where('persona_id', $persona->id)->delete();
        PersonaRed::where('persona_id', $persona->id)->delete();

        $persona->delete();

        $contacto->delete();

        return response()->json([
            'ready' => true,
            'message' => 'El contacto se ha eliminado correctamente',
            'contacto' => $contacto,
        ]);
    }
}
