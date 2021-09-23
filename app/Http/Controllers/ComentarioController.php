<?php

namespace App\Http\Controllers;

use App\Comentario;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;
use Validator;
use DB;

class ComentarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
                'bitacora_id.required' => 'La bitacora es obligatoria.',
                'bitacora_id.exists' => 'Seleccione una bitacora valida.',
                'contenido.required' => 'Contenido es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'bitacora_id' => ['required','exists:bitacoras,id'],
                'contenido' => ['required'],
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
                'bitacora_id' => $request->bitacora_id,
                'contenido' => $request->contenido,
                'user_id' => auth()->user()->id,
            );

            $comentario = Comentario::create($data);

            if (!$comentario->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El comentario no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El comentario se ha creado correctamente',
                'comentario' => $comentario,
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
     * @param  \App\Comentario  $comentario
     * @return \Illuminate\Http\Response
     */
    public function show(Comentario $comentario)
    {
        //
        if(is_null($comentario)){
            return response()->json([
                'ready' => false,
                'message' => 'Comentario no encontrado',
            ], 404);
        }else{

            $comentario->user;

            return response()->json([
                'ready' => true,
                'comentario' => $comentario,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Comentario  $comentario
     * @return \Illuminate\Http\Response
     */
    public function edit(Comentario $comentario)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Comentario  $comentario
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Comentario $comentario)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Comentario  $comentario
     * @return \Illuminate\Http\Response
     */
    public function destroy(Comentario $comentario)
    {
        //
    }
}
