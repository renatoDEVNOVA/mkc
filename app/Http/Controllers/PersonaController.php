<?php

namespace App\Http\Controllers;

use App\Persona;
use Illuminate\Http\Request;

use App\PersonaEmail;
use App\PersonaTelefono;
use App\PersonaDireccion;
use App\PersonaRed;
use App\PersonaHorario;
use App\ProgramaContacto;
use App\ClienteEncargado;
use App\ClienteVocero;
use App\Http\Resources\Persona\PersonaCollection;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class PersonaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $personas = Persona::with(
            'emails.tipoEmail',
            'telefonos.tipoTelefono',
            'categorias',
            'programaContactos.programa.medio',
        )->get();

        return response()->json([
            'ready' => true,
            'personas' => $personas,
        ]);
    }

    public function contactoSelect()
    {
        $contactos = Persona::select('id','nombres','apellidos','tiposPersona')->get()->filter(function($persona){
            return $persona->isContacto();
        });

        return response()->json([
            'ready' => true,
            'contactos' => $contactos->values(),
        ]);
    }

    public function indexV2(){

        $query = request('search', );
        $tipoPersona = request('tipo-persona',);

        if ($query !== 'null' && trim($query) !== '') {
            $persona = Persona::search($query, null, true)->with(
                'emails.tipoEmail',
                'telefonos.tipoTelefono',
                'categorias',
                'programaContactos.programa.medio',
            )->orderBy('id', 'desc');
        } else {
            $persona = Persona::with(
                'emails.tipoEmail',
                'telefonos.tipoTelefono',
                'categorias',
                'programaContactos.programa.medio',
            )->orderBy('id', 'desc');
        }

        /* $persona->nombres = Persona::find(2648)->get()->map(function($item){
            return $item->nombres;
        }); */

        /* DPRP->voceros = DetallePlanMedio::find($DPRP->idDPM)->voceros()->get()->map(function($item){
             . " " . $item->apellidos; */

        if ($tipoPersona !== 'null' && trim($tipoPersona) !== ''){
            $personas = $persona->where('tiposPersona',$tipoPersona);
        }else{
            $personas = $persona;
        }

        return new PersonaCollection($personas->paginate(10));
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
                'fechaNacimiento' => ['nullable','date'],
                'tiposPersona' => ['nullable','array'],
                'tiposPersona.*' => [
                    Rule::in([1, 2]),
                ],
                'categorias' => ['nullable','array'],
                'categorias.*' => ['exists:categorias,id'],
                'temas' => ['nullable','array'],
                'temas.*' => ['exists:temas,id'],
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
                    'message' => 'La persona no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $persona->apodo = isset($request->apodo) ? $request->apodo : null;
            $persona->fechaNacimiento = isset($request->fechaNacimiento) ? $request->fechaNacimiento : null;
            $persona->profesion = isset($request->profesion) ? $request->profesion : null;
            $persona->observacion = isset($request->observacion) ? $request->observacion : null;
            $persona->tiposPersona = isset($request->tiposPersona) ? implode(',', $request->tiposPersona) : null;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona no se ha creado',
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
                            'message' => 'La persona no se ha creado',
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
                            'message' => 'La persona no se ha creado',
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
                        'ubigeo' => $persona_direccion->ubigeo,
                        'idTipoDireccion' => $persona_direccion->idTipoDireccion,
                    );

                    $personaDireccion = PersonaDireccion::create($dataDireccion);
                    if (!$personaDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'La persona no se ha creado',
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
                            'message' => 'La persona no se ha creado',
                        ], 500);
                    }
                }
            }

            if($persona->isContacto()){

                if (isset($request->categorias)) {

                    $persona->categorias()->sync($request->categorias);
    
                }

                if (isset($request->horarios)) {

                    $persona_horarios = json_decode(json_encode($request->horarios));
    
                    foreach ($persona_horarios as $persona_horario) {
                        # code...
                        $dataHorario = array(
                            'persona_id' => $persona->id,
                            'idTipoHorario' => $persona_horario->idTipoHorario,
                            'descripcion' => $persona_horario->descripcion,
                            'periodicidad' => $persona_horario->periodicidad,
                            'horaInicio' => $persona_horario->horaInicio,
                            'horaFin' => $persona_horario->horaFin,
                        );
    
                        $personaHorario = PersonaHorario::create($dataHorario);
                        if (!$personaHorario->id) {
                            DB::rollBack();
                            return response()->json([
                                'ready' => false,
                                'message' => 'La persona no se ha creado',
                            ], 500);
                        }
                    }
                }

                if (isset($request->temas)) {

                    $persona->temas()->sync($request->temas);
    
                }

            }

            if($persona->isVocero()){

                $persona->famoso = isset($request->famoso) ? $request->famoso : null;
                if (!$persona->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'La persona no se ha creado',
                    ], 500);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La persona se ha creado correctamente',
                'persona' => $persona,
                'isContacto' => $persona->isContacto(),
                'isVocero' => $persona->isVocero(),
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
     * @param  \App\Persona  $persona
     * @return \Illuminate\Http\Response
     */
    public function show(Persona $persona)
    {
        //
        if(is_null($persona)){
            return response()->json([
                'ready' => false,
                'message' => 'Persona no encontrado',
            ], 404);
        }else{

            $persona->categorias = $persona->categorias()->get()->map(function($categoria){
                return $categoria->id;
            });

            $persona->temas = $persona->temas()->get()->map(function($tema){
                return $tema->id;
            });

            $persona->emails = $persona->emails()->get()->map(function($email){
                $email->tipoEmail->atributo;
                return $email;
            });
            $persona->telefonos = $persona->telefonos()->get()->map(function($telefono){
                $telefono->tipoTelefono->atributo;
                return $telefono;
            });
            $persona->direccions = $persona->direccions()->get()->map(function($direccion){
                $direccion->tipoDireccion->atributo;
                return $direccion;
            });
            $persona->reds = $persona->reds()->get()->map(function($red){
                $red->tipoRed->atributo;
                return $red;
            });
            $persona->horarios = $persona->horarios()->get()->map(function($horario){
                $horario->tipoHorario->atributo;
                return $horario;
            });

            return response()->json([
                'ready' => true,
                'persona' => $persona,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Persona  $persona
     * @return \Illuminate\Http\Response
     */
    public function edit(Persona $persona)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Persona  $persona
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Persona $persona)
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
                'fechaNacimiento' => ['nullable','date'],
                'tiposPersona' => ['nullable','array'],
                'tiposPersona.*' => [
                    Rule::in([1, 2]),
                ],
                'categorias' => ['nullable','array'],
                'categorias.*' => ['exists:categorias,id'],
                'temas' => ['nullable','array'],
                'temas.*' => ['exists:temas,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $persona->apellidos = $request->apellidos;
            $persona->nombres = $request->nombres;
            $persona->genero = $request->genero;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $persona->apodo = isset($request->apodo) ? $request->apodo : null;
            $persona->fechaNacimiento = isset($request->fechaNacimiento) ? $request->fechaNacimiento : null;
            $persona->profesion = isset($request->profesion) ? $request->profesion : null;
            $persona->observacion = isset($request->observacion) ? $request->observacion : null;
            $persona->tiposPersona = isset($request->tiposPersona) ? implode(',', $request->tiposPersona) : null;
            if (!$persona->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona no se ha actualizado',
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
                            'message' => 'La persona no se ha actualizado',
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
                            'message' => 'La persona no se ha actualizado',
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
                        'ubigeo' => $persona_direccion->ubigeo,
                        'idTipoDireccion' => $persona_direccion->idTipoDireccion,
                    );

                    $personaDireccion = PersonaDireccion::create($dataDireccion);
                    if (!$personaDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'La persona no se ha actualizado',
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
                            'message' => 'La persona no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if($persona->isContacto()){

                if (isset($request->categorias)) {

                    $persona->categorias()->sync($request->categorias);
    
                }

                if (isset($request->horarios)) {

                    // Eliminamos los registros actuales
                    PersonaHorario::where('persona_id', $persona->id)->delete();
    
                    $persona_horarios = json_decode(json_encode($request->horarios));
    
                    foreach ($persona_horarios as $persona_horario) {
                        # code...
                        $dataHorario = array(
                            'persona_id' => $persona->id,
                            'idTipoHorario' => $persona_horario->idTipoHorario,
                            'descripcion' => $persona_horario->descripcion,
                            'periodicidad' => $persona_horario->periodicidad,
                            'horaInicio' => $persona_horario->horaInicio,
                            'horaFin' => $persona_horario->horaFin,
                        );
    
                        $personaHorario = PersonaHorario::create($dataHorario);
                        if (!$personaHorario->id) {
                            DB::rollBack();
                            return response()->json([
                                'ready' => false,
                                'message' => 'La persona no se ha creado',
                            ], 500);
                        }
                    }
                }

                if (isset($request->temas)) {

                    $persona->temas()->sync($request->temas);
    
                }

            }

            if($persona->isVocero()){

                $persona->famoso = isset($request->famoso) ? $request->famoso : null;
                if (!$persona->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'La persona no se ha actualizado',
                    ], 500);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La persona se ha actualizado correctamente',
                'persona' => $persona,
                'isContacto' => $persona->isContacto(),
                'isVocero' => $persona->isVocero(),
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
     * @param  \App\Persona  $persona
     * @return \Illuminate\Http\Response
     */
    public function destroy(Persona $persona)
    {
        //
        try {
            DB::beginTransaction();

            $existsProgramaContacto = ProgramaContacto::where('idContacto', $persona->id)->exists();

            if($existsProgramaContacto){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La persona se encuentra relacionada con diferentes medios.',
                ], 400);
            }

            $existsClienteEncargado = ClienteEncargado::where('idEncargado', $persona->id)->exists();

            if($existsClienteEncargado){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La persona se encuentra relacionada con diferentes clientes.',
                ], 400);
            }

            $existsClienteVocero = ClienteVocero::where('idVocero', $persona->id)->exists();

            if($existsClienteVocero){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La persona se encuentra relacionada con diferentes clientes.',
                ], 400);
            }

            //$persona->categorias()->sync([]);
            //$persona->temas()->sync([]);

            PersonaEmail::where('persona_id', $persona->id)->delete();
            PersonaTelefono::where('persona_id', $persona->id)->delete();
            PersonaDireccion::where('persona_id', $persona->id)->delete();
            PersonaRed::where('persona_id', $persona->id)->delete();
            PersonaHorario::where('persona_id', $persona->id)->delete();

            if (!$persona->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La persona se ha eliminado correctamente',
                'persona' => $persona,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getContactos()
    {
        $contactos = Persona::with('emails.tipoEmail','telefonos.tipoTelefono','direccions.tipoDireccion','reds.tipoRed')->get()->filter(function($persona){
            return $persona->isContacto();
        });

        return response()->json([
            'ready' => true,
            'contactos' => $contactos->values(),
        ]);
    }

    public function getVoceros()
    {
        $voceros = Persona::with('emails.tipoEmail','telefonos.tipoTelefono','direccions.tipoDireccion','reds.tipoRed')->get()->filter(function($persona){
            return $persona->isVocero();
        });

        return response()->json([
            'ready' => true,
            'voceros' => $voceros->values(),
        ]);
    }

    public function getList()
    {
        $personas = Persona::all();

        return response()->json([
            'ready' => true,
            'personas' => $personas,
        ]);
    }

}
