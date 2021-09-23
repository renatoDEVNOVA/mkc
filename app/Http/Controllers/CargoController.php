<?php

namespace App\Http\Controllers;

use App\Cargo;
use Illuminate\Http\Request;

use App\ProgramaContacto;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class CargoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $cargos = Cargo::all();

        return response()->json([
            'ready' => true,
            'cargos' => $cargos,
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
                'descripcion.unique' => 'Ya se encuentra registrado un cargo con la misma descipcion.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:cargos,descripcion,NULL,id,deleted_at,NULL'],
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

            $cargo = Cargo::create($data);

            if (!$cargo->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cargo no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cargo se ha creado correctamente',
                'cargo' => $cargo,
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
     * @param  \App\Cargo  $cargo
     * @return \Illuminate\Http\Response
     */
    public function show(Cargo $cargo)
    {
        //
        if(is_null($cargo)){
            return response()->json([
                'ready' => false,
                'message' => 'Cargo no encontrado',
            ], 404);
        }else{

            return response()->json([
                'ready' => true,
                'cargo' => $cargo,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Cargo  $cargo
     * @return \Illuminate\Http\Response
     */
    public function edit(Cargo $cargo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Cargo  $cargo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Cargo $cargo)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatorio.',
                'descripcion.unique' => 'Ya se encuentra registrada un cargo con la misma descipcion.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:cargos,descripcion,' . $cargo->id . ',id,deleted_at,NULL'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $cargo->descripcion = $request->descripcion;
            if (!$cargo->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cargo no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cargo se ha actualizado correctamente',
                'cargo' => $cargo,
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
     * @param  \App\Cargo  $cargo
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cargo $cargo)
    {
        //
        try {
            DB::beginTransaction();

            $countProgramaContacto = ProgramaContacto::all()->filter(function ($programaContacto) use ($cargo){
                $idsCargo = explode(',', $programaContacto->idsCargo);
                return in_array($cargo->id, $idsCargo);
            })->count();

            if($countProgramaContacto > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El cargo se encuentra relacionado con diferentes contactos.',
                    'countProgramaContacto' => $countProgramaContacto,
                ], 400);
            }

            if (!$cargo->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cargo no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cargo se ha eliminado correctamente',
                'cargo' => $cargo,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
