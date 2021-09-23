<?php

namespace App\Http\Controllers;

use App\MedioPlataforma;
use Illuminate\Http\Request;

use App\ProgramaPlataforma;
use App\ProgramaContacto;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class MedioPlataformaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $medioPlataformas = MedioPlataforma::all();

        return response()->json([
            'ready' => true,
            'medioPlataformas' => $medioPlataformas,
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
                'valor.required' => 'El valor es obligatorio.',
                'valor.unique' => 'Ya se encuentra registrada la plataforma deseada con el mismo valor.',
                'medio_id.required' => 'El medio es obligatorio.',
                'medio_id.exists' => 'Seleccione un medio valido.',
                'idPlataformaClasificacion.required' => 'La plataforma es obligatoria.',
                'idPlataformaClasificacion.exists' => 'Seleccione una plataforma valida.',
            ];

            $validator = Validator::make($request->all(), [
                'valor' => [
                    'required',
                    Rule::unique('medio_plataformas')->where(function ($query) use ($request){
                        return $query->where('medio_id', $request->medio_id)->where('idPlataformaClasificacion', $request->idPlataformaClasificacion)->whereNull('deleted_at');
                    }),
                ],
                'medio_id' => ['required','exists:medios,id'],
                'idPlataformaClasificacion' => ['required','exists:plataforma_clasificacions,id'],
                'alcance' => ['nullable','integer'],
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
                'medio_id' => $request->medio_id,
                'idPlataformaClasificacion' => $request->idPlataformaClasificacion,
                'valor' => $request->valor,
            );

            $medioPlataforma = MedioPlataforma::create($data);

            if (!$medioPlataforma->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del medio no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $medioPlataforma->alcance = isset($request->alcance) ? $request->alcance : null;
            $medioPlataforma->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$medioPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del medio no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del medio se ha creado correctamente',
                'medioPlataforma' => $medioPlataforma,
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
     * @param  \App\MedioPlataforma  $medioPlataforma
     * @return \Illuminate\Http\Response
     */
    public function show(MedioPlataforma $medioPlataforma)
    {
        //
        if(is_null($medioPlataforma)){
            return response()->json([
                'ready' => false,
                'message' => 'La plataforma del medio no se pudo encontrar',
            ], 404);
        }else{

            $medioPlataforma->medio;
            $medioPlataforma->plataformaClasificacion;

            return response()->json([
                'ready' => true,
                'medioPlataforma' => $medioPlataforma,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\MedioPlataforma  $medioPlataforma
     * @return \Illuminate\Http\Response
     */
    public function edit(MedioPlataforma $medioPlataforma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\MedioPlataforma  $medioPlataforma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MedioPlataforma $medioPlataforma)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'valor.required' => 'El valor es obligatorio.',
                'valor.unique' => 'Ya se encuentra registrada la plataforma deseada con el mismo valor.',
            ];

            $validator = Validator::make($request->all(), [
                'valor' => [
                    'required',
                    Rule::unique('medio_plataformas')->ignore($medioPlataforma->id)->where(function ($query) use ($medioPlataforma){
                        return $query->where('medio_id', $medioPlataforma->medio_id)->where('idPlataformaClasificacion', $medioPlataforma->idPlataformaClasificacion)->whereNull('deleted_at');
                    }),
                ],
                'alcance' => ['nullable','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $medioPlataforma->valor = $request->valor;
            if (!$medioPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del medio no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $medioPlataforma->alcance = isset($request->alcance) ? $request->alcance : null;
            $medioPlataforma->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$medioPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del medio no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del medio se ha actualizado correctamente',
                'medioPlataforma' => $medioPlataforma,
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
     * @param  \App\MedioPlataforma  $medioPlataforma
     * @return \Illuminate\Http\Response
     */
    public function destroy(MedioPlataforma $medioPlataforma)
    {
        //
        try {
            DB::beginTransaction();

            $existsProgramaPlataforma = ProgramaPlataforma::where('idMedioPlataforma', $medioPlataforma->id)->exists();

            if($existsProgramaPlataforma){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La plataforma se encuentra relacionada con diferentes programas.',
                ], 400);
            }

            $countProgramaContacto = ProgramaContacto::all()->filter(function ($programaContacto) use ($medioPlataforma){
                $idsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);
                return in_array($medioPlataforma->id, $idsMedioPlataforma);
            })->count();

            if($countProgramaContacto > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La plataforma se encuentra relacionada con diferentes contactos.',
                    'countProgramaContacto' => $countProgramaContacto,
                ], 400);
            }

            $countDetallePlanMedio = DetallePlanMedio::all()->filter(function ($detallePlanMedio) use ($medioPlataforma){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
                return in_array($medioPlataforma->id, $idsMedioPlataforma);
            })->count();

            if($countDetallePlanMedio > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La plataforma se encuentra relacionada con diferentes publicaciones.',
                    'countDetallePlanMedio' => $countDetallePlanMedio,
                ], 400);
            }

            if (!$medioPlataforma->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del medio no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del medio se ha eliminado correctamente',
                'medioPlataforma' => $medioPlataforma,
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
        $medioPlataformas = MedioPlataforma::with('medio','plataformaClasificacion.plataforma')->get()->filter(function ($medioPlataforma) use ($idMedio){
            return $medioPlataforma->medio_id == $idMedio;
        });

        return response()->json([
            'ready' => true,
            'medioPlataformas' => $medioPlataformas->values(),
        ]);
    }
}
