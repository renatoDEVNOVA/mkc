<?php

namespace App\Http\Controllers;

use App\Plataforma;
use Illuminate\Http\Request;

use App\PlataformaClasificacion;

use Validator;
use DB;

class PlataformaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $plataformas = Plataforma::all();

        return response()->json([
            'ready' => true,
            'plataformas' => $plataformas,
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
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required'],
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
            );

            $plataforma = Plataforma::create($data);

            if (!$plataforma->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma se ha creado correctamente',
                'plataforma' => $plataforma,
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
     * @param  \App\Plataforma  $plataforma
     * @return \Illuminate\Http\Response
     */
    public function show(Plataforma $plataforma)
    {
        //
        if(is_null($plataforma)){
            return response()->json([
                'ready' => false,
                'message' => 'Plataforma no encontrada',
            ], 404);
        }else{

            return response()->json([
                'ready' => true,
                'plataforma' => $plataforma,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Plataforma  $plataforma
     * @return \Illuminate\Http\Response
     */
    public function edit(Plataforma $plataforma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Plataforma  $plataforma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Plataforma $plataforma)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $plataforma->descripcion = $request->descripcion;
            if (!$plataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma se ha actualizado correctamente',
                'plataforma' => $plataforma,
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
     * @param  \App\Plataforma  $plataforma
     * @return \Illuminate\Http\Response
     */
    public function destroy(Plataforma $plataforma)
    {
        //
        try {
            DB::beginTransaction();

            $existsPlataformaClasificacion = PlataformaClasificacion::where('plataforma_id', $plataforma->id)->exists();

            if($existsPlataformaClasificacion){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La plataforma se encuentra relacionada con diferentes clasificaciones.',
                ], 400);
            }

            if (!$plataforma->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma se ha eliminado correctamente',
                'plataforma' => $plataforma,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
