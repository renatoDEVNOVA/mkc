<?php

namespace App\Http\Controllers;

use App\ProgramaContacto;
use Illuminate\Http\Request;

use App\Persona;
use App\Cargo;
use App\Atributo;
use App\TipoAtributo;
use App\MedioPlataforma;
use App\ProgramaPlataforma;
use App\DetallePlanMedio;
use Validator;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;

class ProgramaContactoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $programaContactos = ProgramaContacto::all();

        return response()->json([
            'ready' => true,
            'programaContactos' => $programaContactos,
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
                'idContacto.required' => 'El contacto es obligatorio.',
                'idContacto.exists' => 'Seleccione un contacto valido.',
                'idContacto.unique' => 'Ya se encuentra asignado el contacto al programa deseado.',
                'programa_id.required' => 'El programa es obligatorio.',
                'programa_id.exists' => 'Seleccione un programa valido.',
                'tipoInfluencia.required' => 'La Influencia es obligatorio.',
                'tipoInfluencia.integer' => 'Seleccione una Influencia valida.',
                'idsCargo.required' => 'Cargos es obligatorio.',
                'idsCargo.*.exists' => 'Seleccione cargos validos.',
                'idsMedioPlataforma.required' => 'Plataformas es obligatorio.',
                'idsMedioPlataforma.*.exists' => 'Seleccione plataformas validas.',
            ];

            $validator = Validator::make($request->all(), [
                'idContacto' => [
                    'required',
                    'exists:personas,id',
                    Rule::unique('programa_contactos')->where(function ($query) use ($request){
                        return $query->where('programa_id', $request->programa_id)->whereNull('deleted_at');
                    }),
                ],
                'programa_id' => ['required','exists:programas,id'],
                'tipoInfluencia' => ['required','integer'],
                'idsCargo' => ['required','array'],
                'idsCargo.*' => ['exists:cargos,id'],
                'idsMedioPlataforma' => ['required','array'],
                'idsMedioPlataforma.*' => [
                    Rule::exists('programa_plataformas','idMedioPlataforma')->where(function ($query) use ($request){
                        $query->where('programa_id', $request->programa_id);
                    }),
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $persona = Persona::find($request->idContacto);
            if(!$persona->isContacto()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un contacto',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'programa_id' => $request->programa_id,
                'idContacto' => $request->idContacto,
                'tipoInfluencia' => $request->tipoInfluencia,
                'idsCargo' => implode(',', $request->idsCargo),
                'idsMedioPlataforma' => implode(',', $request->idsMedioPlataforma),
            );

            $programaContacto = ProgramaContacto::create($data);

            if (!$programaContacto->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no ha sido asignado al programa deseado',
                ], 500);
            }

            // Datos Opcionales
            $programaContacto->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$programaContacto->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El contacto no ha sido asignado al programa deseado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El contacto ha sido asignado correctamente al programa deseado',
                'programaContacto' => $programaContacto,
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
     * @param  \App\ProgramaContacto  $programaContacto
     * @return \Illuminate\Http\Response
     */
    public function show(ProgramaContacto $programaContacto)
    {
        //
        if(is_null($programaContacto)){
            return response()->json([
                'ready' => false,
                'message' => 'El registro no se pudo encontrar',
            ], 404);
        }else{

            $programaContacto->programa->medio;


            $idsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);
            
            $idProgramaPlataformas = ProgramaPlataforma::where('programa_id',$programaContacto->programa_id)
            ->get()->map(function($programaPlataforma){
                return $programaPlataforma['idMedioPlataforma'];
            });

            $programaContacto->contacto;
            $idsCargo = explode(',', $programaContacto->idsCargo);
            $programaContacto->cargos = Cargo::whereIn('id', $idsCargo)->get();

            $allIdsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);

            /* print_r( ($idsMedioPlataforma));
            exit; */

            $idsMedioPlataforma = array_intersect($allIdsMedioPlataforma,$idProgramaPlataformas->toArray());

            $programaContacto->idsMedioPlataforma = implode(",", $idsMedioPlataforma);
            

            $programaContacto->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });

            return response()->json([
                'ready' => true,
                'programaContacto' => $programaContacto,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ProgramaContacto  $programaContacto
     * @return \Illuminate\Http\Response
     */
    public function edit(ProgramaContacto $programaContacto)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ProgramaContacto  $programaContacto
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProgramaContacto $programaContacto)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'tipoInfluencia.required' => 'La Influencia es obligatorio.',
                'tipoInfluencia.integer' => 'Seleccione una Influencia valida.',
                'idsCargo.required' => 'Cargos es obligatorio.',
                'idsCargo.*.exists' => 'Seleccione cargos validos.',
                'idsMedioPlataforma.required' => 'Plataformas es obligatorio.',
                'idsMedioPlataforma.*.exists' => 'Seleccione plataformas validas.',
            ];

            $validator = Validator::make($request->all(), [
                'tipoInfluencia' => ['required','integer'],
                'idsCargo' => ['required','array'],
                'idsCargo.*' => ['exists:cargos,id'],
                'idsMedioPlataforma' => ['required','array'],
                'idsMedioPlataforma.*' => [
                    Rule::exists('programa_plataformas','idMedioPlataforma')->where(function ($query) use ($programaContacto){
                        $query->where('programa_id', $programaContacto->programa_id);
                    }),
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }
            
            $persona = Persona::find($programaContacto->idContacto);
            if(!$persona->isContacto()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un contacto',
                ], 400);
            }

            // Datos Obligatorios
            $programaContacto->tipoInfluencia = $request->tipoInfluencia;
            $programaContacto->idsCargo = implode(',', $request->idsCargo);
            $programaContacto->idsMedioPlataforma = implode(',', $request->idsMedioPlataforma);
            if (!$programaContacto->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $programaContacto->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$programaContacto->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha actualizado correctamente',
                'programaContacto' => $programaContacto,
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
     * @param  \App\ProgramaContacto  $programaContacto
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProgramaContacto $programaContacto)
    {
        //
        try {
            DB::beginTransaction();

            $existsDetallePlanMedio = DetallePlanMedio::where('idProgramaContacto', $programaContacto->id)->exists();

            if($existsDetallePlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El registro se encuentra relacionado con diferentes publicaciones.',
                ], 400);
            }

            if (!$programaContacto->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha eliminado correctamente',
                'programaContacto' => $programaContacto,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByMedio($idMedio)
    {
        $programaContactos = ProgramaContacto::with('programa.medio','contacto','programa.programaPlataformas')->whereNull('deleted_at')->get()->filter(function ($programaContacto) use ($idMedio){
            return $programaContacto->programa->medio_id == $idMedio;
        })->map(function($programaContacto){
            $idsCargo = explode(',', $programaContacto->idsCargo);

            $programaContacto->cargos = Cargo::whereIn('id', $idsCargo)->get();

            $idsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);

            $idProgramaPlataformas = ProgramaPlataforma::where('programa_id',$programaContacto->programa_id)
            ->get()->map(function($programaPlataforma){
                return $programaPlataforma['idMedioPlataforma'];
            });

            $allIdsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);

            $idsMedioPlataforma = array_intersect($allIdsMedioPlataforma,$idProgramaPlataformas->toArray());

            $programaContacto->idsMedioPlataforma = implode(",", $idsMedioPlataforma);

            $programaContacto->medioPlataformas = MedioPlataforma::with('plataformaClasificacion.plataforma')->whereIn('id', $idsMedioPlataforma)->whereNull('deleted_at')->get();

            return $programaContacto;
        });

        return response()->json([
            'ready' => true,
            'programaContactos' => $programaContactos->values(),
        ]);
    }

    public function getListByContacto($idContacto)
    {
        $programaContactos = ProgramaContacto::with('programa.medio','contacto')->where('idContacto', $idContacto)->get()->map(function($programaContacto){

            $idsCargo = explode(',', $programaContacto->idsCargo);
            $programaContacto->cargos = Cargo::whereIn('id', $idsCargo)->get();

            $idsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);
            $programaContacto->medioPlataformas = MedioPlataforma::with('plataformaClasificacion.plataforma')->whereIn('id', $idsMedioPlataforma)->get();

            return $programaContacto;
        });

        return response()->json([
            'ready' => true,
            'programaContactos' => $programaContactos->values(),
        ]);
    }

    public function activate($id)
    {
        //
        try {
            DB::beginTransaction();

            $programaContacto = ProgramaContacto::find($id);
            $programaContacto->activo = 1;
            if (!$programaContacto->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha activado',
                ], 500);
            }

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (isset($response->json()['service']) && ($response->json()['service'] == "ok")) {

                $data = array(
                    'idContactoMedio' => "{$programaContacto->id}",
                    'activar' => "1",
                );

                // Activamos los registros
                $response = Http::asForm()->post('http://18.218.39.117:8000/casos/cambiarestado', [
                    'securitytoken' => 123456,
                    'data' => json_encode($data),
                ]);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha activado correctamente',
                'programaContacto' => $programaContacto,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function deactivate($id)
    {
        //
        try {
            DB::beginTransaction();

            $programaContacto = ProgramaContacto::find($id);
            $programaContacto->activo = 0;
            if (!$programaContacto->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha desactivado',
                ], 500);
            }

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (isset($response->json()['service']) && ($response->json()['service'] == "ok")) {

                $data = array(
                    'idContactoMedio' => "{$programaContacto->id}",
                    'activar' => "0",
                );

                // Activamos los registros
                $response = Http::asForm()->post('http://18.218.39.117:8000/casos/cambiarestado', [
                    'securitytoken' => 123456,
                    'data' => json_encode($data),
                ]);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha desactivado correctamente',
                'programaContacto' => $programaContacto,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
