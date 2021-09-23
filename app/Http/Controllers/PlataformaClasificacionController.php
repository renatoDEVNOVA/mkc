<?php

namespace App\Http\Controllers;

use App\PlataformaClasificacion;
use Illuminate\Http\Request;

use App\MedioPlataforma;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class PlataformaClasificacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $plataformaClasificacions = PlataformaClasificacion::with('plataforma')->get();

        return response()->json([
            'ready' => true,
            'plataformaClasificacions' => $plataformaClasificacions,
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
                'descripcion.required' => 'La descripcion es obligatoria.',
                'descripcion.unique' => 'Ya se encuentra registrada la clasificacion deseada con la misma descripcion.',
                'plataforma_id.required' => 'La plataforma es obligatoria.',
                'plataforma_id.exists' => 'Seleccione una plataforma valida.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => [
                    'required',
                    Rule::unique('plataforma_clasificacions')->where(function ($query) use ($request){
                        return $query->where('plataforma_id', $request->plataforma_id)->whereNull('deleted_at');
                    }),
                ],
                'plataforma_id' => ['required','exists:plataformas,id'],
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
                'descripcion' => $request->descripcion,
                'plataforma_id' => $request->plataforma_id,
            );

            $plataformaClasificacion = PlataformaClasificacion::create($data);

            if (!$plataformaClasificacion->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La clasificacion no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La clasificacion se ha creado correctamente',
                'plataformaClasificacion' => $plataformaClasificacion,
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
     * @param  \App\PlataformaClasificacion  $plataformaClasificacion
     * @return \Illuminate\Http\Response
     */
    public function show(PlataformaClasificacion $plataformaClasificacion)
    {
        //
        if(is_null($plataformaClasificacion)){
            return response()->json([
                'ready' => false,
                'message' => 'La clasificacion no se pudo encontrar',
            ], 404);
        }else{

            $plataformaClasificacion->plataforma;

            return response()->json([
                'ready' => true,
                'plataformaClasificacion' => $plataformaClasificacion,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PlataformaClasificacion  $plataformaClasificacion
     * @return \Illuminate\Http\Response
     */
    public function edit(PlataformaClasificacion $plataformaClasificacion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PlataformaClasificacion  $plataformaClasificacion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PlataformaClasificacion $plataformaClasificacion)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatoria.',
                'descripcion.unique' => 'Ya se encuentra registrada la clasificacion deseada con la misma descripcion.',
                //'plataforma_id.required' => 'La plataforma es obligatoria.',
                //'plataforma_id.exists' => 'Seleccione una plataforma valida.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => [
                    'required',
                    Rule::unique('plataforma_clasificacions')->ignore($plataformaClasificacion->id)->where(function ($query) use ($plataformaClasificacion){
                        return $query->where('plataforma_id', $plataformaClasificacion->plataforma_id)->whereNull('deleted_at');
                    }),
                ],
                //'plataforma_id' => ['required','exists:plataformas,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $plataformaClasificacion->descripcion = $request->descripcion;
            //$plataformaClasificacion->plataforma_id = $request->plataforma_id;
            if (!$plataformaClasificacion->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La clasificacion no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La clasificacion se ha actualizado correctamente',
                'plataformaClasificacion' => $plataformaClasificacion,
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
     * @param  \App\PlataformaClasificacion  $plataformaClasificacion
     * @return \Illuminate\Http\Response
     */
    public function destroy(PlataformaClasificacion $plataformaClasificacion)
    {
        //
        try {
            DB::beginTransaction();

            $existsMedioPlataforma = MedioPlataforma::where('idPlataformaClasificacion', $plataformaClasificacion->id)->exists();

            if($existsMedioPlataforma){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La clasificacion se encuentra relacionada con diferentes medios.',
                ], 400);
            }

            if (!$plataformaClasificacion->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La clasificacion no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La clasificacion se ha eliminado correctamente',
                'plataformaClasificacion' => $plataformaClasificacion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
