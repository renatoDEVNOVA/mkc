<?php

namespace App\Http\Controllers;

use App\Medio;
use Illuminate\Http\Request;

use App\MedioEmail;
use App\MedioTelefono;
use App\MedioDireccion;
use App\MedioRed;
use App\MedioPlataforma;
use App\Programa;
use App\ProgramaPlataforma;
use App\ProgramaContacto;
use Validator;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class MedioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $medios = Medio::with(
            'company',
            'medioPadre',
            'emails.tipoEmail',
            'telefonos.tipoTelefono',
            'direccions.tipoDireccion',
            'reds.tipoRed',
            'programas',
        )->get();

        return response()->json([
            'ready' => true,
            'medios' => $medios,
        ]);
    }

    public function mediosSelect(){
        
        $medios = Medio::select('nombre','id')->get();

        return response()->json([
            'ready' => true,
            'medios' => $medios,
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
                'nombre.required' => 'El Nombre es obligatorio.',
                'tipoRegion.required' => 'Region es obligatorio.',
                'tipoRegion.in' => 'Selecione Region valido.',
                'company_id.exists' => 'Seleccione una Compañia valida.',
                'filial.required' => 'Filial es obligatorio.',
                'filial.boolean' => 'Seleccione Filial valido.',
                'idMedioPadre.required_if' => 'El Medio Principal es obligatorio cuando es una filial.',
                'idMedioPadre.exists' => 'Seleccione un Medio valido.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required'],
                'tipoRegion' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'company_id' => ['nullable','exists:companies,id'],
                'filial' => ['required','boolean'],
                'idMedioPadre' => ['required_if:filial,1','exists:medios,id'],
            ], $messages);

            $validator->sometimes('idMedioPadre', 'nullable', function ($request) {
                return !$request->filial;
            });

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'nombre' => $request->nombre,
                'tipoRegion' => $request->tipoRegion,
                'filial' => $request->filial,
            );

            $medio = Medio::create($data);

            if (!$medio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El medio no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $medio->alias = isset($request->alias) ? $request->alias : null;
            $medio->company_id = isset($request->company_id) ? $request->company_id : null;
            $medio->idMedioPadre = $request->filial ? (isset($request->idMedioPadre) ? $request->idMedioPadre : null) : null;
            $medio->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$medio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El medio no se ha creado',
                ], 500);
            }

            if (isset($request->emails)) {

                $medio_emails = json_decode(json_encode($request->emails));

                foreach ($medio_emails as $medio_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$medio_email->email.' se encuentra duplicado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $medio_email->email), [
                        'email' => [
                            'email',
                            Rule::unique('medio_emails')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'medio_email' => $medio_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'medio_id' => $medio->id,
                        'email' => $medio_email->email,
                        'idTipoEmail' => $medio_email->idTipoEmail,
                    );

                    $medioEmail = MedioEmail::create($dataEmail);
                    if (!$medioEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->telefonos)) {

                $medio_telefonos = json_decode(json_encode($request->telefonos));

                foreach ($medio_telefonos as $medio_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$medio_telefono->telefono.' se encuentra duplicado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $medio_telefono->telefono), [
                        'telefono' => [
                            Rule::unique('medio_telefonos')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'medio_telefono' => $medio_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'medio_id' => $medio->id,
                        'telefono' => $medio_telefono->telefono,
                        'idTipoTelefono' => $medio_telefono->idTipoTelefono,
                    );

                    $medioTelefono = MedioTelefono::create($dataTelefono);
                    if (!$medioTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->direccions)) {

                $medio_direccions = json_decode(json_encode($request->direccions));

                foreach ($medio_direccions as $medio_direccion) {
                    # code...

                    $messagesDireccion = [
                        'direccion.unique' => 'La direccion '.$medio_direccion->direccion.' se encuentra duplicada.',
                    ];
    
                    $validatorDireccion = Validator::make(array('direccion' => $medio_direccion->direccion), [
                        'direccion' => [
                            Rule::unique('medio_direccions')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesDireccion);

                    if ($validatorDireccion->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorDireccion->errors(),
                            'medio_direccion' => $medio_direccion,
                        ], 400);
                    }

                    $dataDireccion = array(
                        'medio_id' => $medio->id,
                        'direccion' => $medio_direccion->direccion,
                        'idTipoDireccion' => $medio_direccion->idTipoDireccion,
                    );

                    $medioDireccion = MedioDireccion::create($dataDireccion);
                    if (!$medioDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->reds)) {

                $medio_reds = json_decode(json_encode($request->reds));

                foreach ($medio_reds as $medio_red) {
                    # code...

                    $messagesRed = [
                        'red.unique' => 'La red social '.$medio_red->red.' se encuentra duplicada.',
                    ];
    
                    $validatorRed = Validator::make(array('red' => $medio_red->red), [
                        'red' => [
                            Rule::unique('medio_reds')->where(function ($query) use ($medio_red, $medio){
                                return $query->where('medio_id', $medio->id)->where('idTipoRed', $medio_red->idTipoRed)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesRed);

                    if ($validatorRed->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorRed->errors(),
                            'medio_red' => $medio_red,
                        ], 400);
                    }

                    $dataRed = array(
                        'medio_id' => $medio->id,
                        'red' => $medio_red->red,
                        'idTipoRed' => $medio_red->idTipoRed,
                    );

                    $medioRed = MedioRed::create($dataRed);
                    if (!$medioRed->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha creado',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El medio se ha creado correctamente',
                'medio' => $medio,
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
     * @param  \App\Medio  $medio
     * @return \Illuminate\Http\Response
     */
    public function show(Medio $medio)
    {
        //
        if(is_null($medio)){
            return response()->json([
                'ready' => false,
                'message' => 'Medio no encontrado',
            ], 404);
        }else{

            $medio->company;
            $medio->medioPadre;
            $medio->emails = $medio->emails()->get()->map(function($email){
                $email->tipoEmail->atributo;
                return $email;
            });
            $medio->telefonos = $medio->telefonos()->get()->map(function($telefono){
                $telefono->tipoTelefono->atributo;
                return $telefono;
            });
            $medio->direccions = $medio->direccions()->get()->map(function($direccion){
                $direccion->tipoDireccion->atributo;
                return $direccion;
            });
            $medio->reds = $medio->reds()->get()->map(function($red){
                $red->tipoRed->atributo;
                return $red;
            });
            $medio->medioPlataformas = $medio->medioPlataformas()->get()->map(function($medioPlataforma){
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });
            $medio->programas;

            return response()->json([
                'ready' => true,
                'medio' => $medio,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Medio  $medio
     * @return \Illuminate\Http\Response
     */
    public function edit(Medio $medio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Medio  $medio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Medio $medio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombre.required' => 'El Nombre es obligatorio.',
                'tipoRegion.required' => 'Region es obligatorio.',
                'tipoRegion.in' => 'Selecione Region valido.',
                'company_id.exists' => 'Seleccione una Compañia valida.',
                'filial.required' => 'Filial es obligatorio.',
                'filial.boolean' => 'Seleccione Filial valido.',
                'idMedioPadre.required_if' => 'El Medio Principal es obligatorio cuando es una filial.',
                'idMedioPadre.exists' => 'Seleccione un Medio valido.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required'],
                'tipoRegion' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'company_id' => ['nullable','exists:companies,id'],
                'filial' => ['required','boolean'],
                //'idMedioPadre' => ['required_if:filial,1','exists:medios,id'],
                'idMedioPadre' => [
                    'required_if:filial,1',
                    Rule::exists('medios','id')->where(function ($query) use ($medio){
                        $query->where('id', '!=', $medio->id);
                    }),
                ],
            ], $messages);

            $validator->sometimes('idMedioPadre', 'nullable', function ($request) {
                return !$request->filial;
            });

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $medio->nombre = $request->nombre;
            $medio->tipoRegion = $request->tipoRegion;
            $medio->filial = $request->filial;
            if (!$medio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El medio no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $medio->alias = isset($request->alias) ? $request->alias : null;
            $medio->company_id = isset($request->company_id) ? $request->company_id : null;
            $medio->idMedioPadre = $request->filial ? (isset($request->idMedioPadre) ? $request->idMedioPadre : null) : null;
            $medio->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$medio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El medio no se ha actualizado',
                ], 500);
            }

            if (isset($request->emails)) {

                // Eliminamos los registros actuales
                MedioEmail::where('medio_id', $medio->id)->delete();

                $medio_emails = json_decode(json_encode($request->emails));

                foreach ($medio_emails as $medio_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$medio_email->email.' se encuentra duplicado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $medio_email->email), [
                        'email' => [
                            'email',
                            Rule::unique('medio_emails')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'medio_email' => $medio_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'medio_id' => $medio->id,
                        'email' => $medio_email->email,
                        'idTipoEmail' => $medio_email->idTipoEmail,
                    );

                    $medioEmail = MedioEmail::create($dataEmail);
                    if (!$medioEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->telefonos)) {

                // Eliminamos los registros actuales
                MedioTelefono::where('medio_id', $medio->id)->delete();

                $medio_telefonos = json_decode(json_encode($request->telefonos));

                foreach ($medio_telefonos as $medio_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$medio_telefono->telefono.' se encuentra duplicado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $medio_telefono->telefono), [
                        'telefono' => [
                            Rule::unique('medio_telefonos')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'medio_telefono' => $medio_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'medio_id' => $medio->id,
                        'telefono' => $medio_telefono->telefono,
                        'idTipoTelefono' => $medio_telefono->idTipoTelefono,
                    );

                    $medioTelefono = MedioTelefono::create($dataTelefono);
                    if (!$medioTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->direccions)) {

                // Eliminamos los registros actuales
                MedioDireccion::where('medio_id', $medio->id)->delete();

                $medio_direccions = json_decode(json_encode($request->direccions));

                foreach ($medio_direccions as $medio_direccion) {
                    # code...

                    $messagesDireccion = [
                        'direccion.unique' => 'La direccion '.$medio_direccion->direccion.' se encuentra duplicada.',
                    ];
    
                    $validatorDireccion = Validator::make(array('direccion' => $medio_direccion->direccion), [
                        'direccion' => [
                            Rule::unique('medio_direccions')->where(function ($query) use ($medio){
                                return $query->where('medio_id', $medio->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesDireccion);

                    if ($validatorDireccion->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorDireccion->errors(),
                            'medio_direccion' => $medio_direccion,
                        ], 400);
                    }

                    $dataDireccion = array(
                        'medio_id' => $medio->id,
                        'direccion' => $medio_direccion->direccion,
                        'idTipoDireccion' => $medio_direccion->idTipoDireccion,
                    );

                    $medioDireccion = MedioDireccion::create($dataDireccion);
                    if (!$medioDireccion->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->reds)) {

                // Eliminamos los registros actuales
                MedioRed::where('medio_id', $medio->id)->delete();

                $medio_reds = json_decode(json_encode($request->reds));

                foreach ($medio_reds as $medio_red) {
                    # code...

                    $messagesRed = [
                        'red.unique' => 'La red social '.$medio_red->red.' se encuentra duplicada.',
                    ];
    
                    $validatorRed = Validator::make(array('red' => $medio_red->red), [
                        'red' => [
                            Rule::unique('medio_reds')->where(function ($query) use ($medio_red, $medio){
                                return $query->where('medio_id', $medio->id)->where('idTipoRed', $medio_red->idTipoRed)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesRed);

                    if ($validatorRed->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorRed->errors(),
                            'medio_red' => $medio_red,
                        ], 400);
                    }

                    $dataRed = array(
                        'medio_id' => $medio->id,
                        'red' => $medio_red->red,
                        'idTipoRed' => $medio_red->idTipoRed,
                    );

                    $medioRed = MedioRed::create($dataRed);
                    if (!$medioRed->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El medio no se ha actualizado',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El medio se ha actualizado correctamente',
                'medio' => $medio,
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
     * @param  \App\Medio  $medio
     * @return \Illuminate\Http\Response
     */
    public function destroy(Medio $medio)
    {
        //
        try {
            DB::beginTransaction();

            $existsProgramaContacto = ProgramaContacto::whereHas('programa', function (Builder $query) use ($medio){
                $query->where('medio_id', $medio->id);
            })->has('detallePlanMedios')->exists();

            if($existsProgramaContacto){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El medio se encuentra relacionado con diferentes contactos.',
                ], 400);
            }

            ProgramaPlataforma::whereHas('programa', function (Builder $query) use ($medio){
                $query->where('medio_id', $medio->id);
            })->delete();

            Programa::where('medio_id', $medio->id)->delete();

            MedioPlataforma::where('medio_id', $medio->id)->delete();

            MedioEmail::where('medio_id', $medio->id)->delete();
            MedioTelefono::where('medio_id', $medio->id)->delete();
            MedioDireccion::where('medio_id', $medio->id)->delete();
            MedioRed::where('medio_id', $medio->id)->delete();

            if (!$medio->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El medio no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El medio se ha eliminado correctamente',
                'medio' => $medio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }
}
