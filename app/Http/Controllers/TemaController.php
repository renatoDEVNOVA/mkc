<?php

namespace App\Http\Controllers;

use App\Tema;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class TemaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $temas = Tema::all();

        return response()->json([
            'ready' => true,
            'temas' => $temas,
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
                'descripcion.required' => 'La descripcion es obligatorio.',
                'descripcion.unique' => 'Ya se encuentra registrado un tema con la misma descipcion.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:temas,descripcion,NULL,id,deleted_at,NULL'],
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

            $tema = Tema::create($data);

            if (!$tema->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tema no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tema se ha creado correctamente',
                'tema' => $tema,
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
     * @param  \App\Tema  $tema
     * @return \Illuminate\Http\Response
     */
    public function show(Tema $tema)
    {
        //
        if(is_null($tema)){
            return response()->json([
                'ready' => false,
                'message' => 'Tema no encontrado',
            ], 404);
        }else{

            return response()->json([
                'ready' => true,
                'tema' => $tema,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Tema  $tema
     * @return \Illuminate\Http\Response
     */
    public function edit(Tema $tema)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Tema  $tema
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Tema $tema)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatorio.',
                'descripcion.unique' => 'Ya se encuentra registrada un tema con la misma descipcion.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:temas,descripcion,' . $tema->id . ',id,deleted_at,NULL'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $tema->descripcion = $request->descripcion;
            if (!$tema->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tema no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tema se ha actualizado correctamente',
                'tema' => $tema,
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
     * @param  \App\Tema  $tema
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tema $tema)
    {
        //
        try {
            DB::beginTransaction();

            $countPersonas = $tema->personas()->count();

            if($countPersonas > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El tema se encuentra relacionado con diferentes personas.',
                ], 400);
            }

            if (!$tema->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tema no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tema se ha eliminado correctamente',
                'tema' => $tema,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
