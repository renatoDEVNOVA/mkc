<?php

namespace App\Http\Controllers;

use App\Programa;
use Illuminate\Http\Request;

use App\ProgramaContacto;
use App\ProgramaPlataforma;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class ProgramaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $programas = Programa::all();

        return response()->json([
            'ready' => true,
            'programas' => $programas,
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
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.unique' => 'Ya se encuentra registrado el programa deseado con el mismo nombre.',
                'medio_id.required' => 'El medio es obligatoria.',
                'medio_id.exists' => 'Seleccione un medio valido.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => [
                    'required',
                    Rule::unique('programas')->where(function ($query) use ($request){
                        return $query->where('medio_id', $request->medio_id)->whereNull('deleted_at');
                    }),
                ],
                'medio_id' => ['required','exists:medios,id'],
                'categorias' => ['nullable','array'],
                'categorias.*' => ['exists:categorias,id'],
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
                'nombre' => $request->nombre,
                'medio_id' => $request->medio_id,
            );

            $programa = Programa::create($data);

            if (!$programa->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El programa no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $programa->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            $programa->periodicidad = isset($request->periodicidad) ? $request->periodicidad : null;
            if (!$programa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El programa no se ha creado',
                ], 500);
            }

            if (isset($request->categorias)) {

                $programa->categorias()->sync($request->categorias);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El programa se ha creado correctamente',
                'programa' => $programa,
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
     * @param  \App\Programa  $programa
     * @return \Illuminate\Http\Response
     */
    public function show(Programa $programa)
    {
        //
        if(is_null($programa)){
            return response()->json([
                'ready' => false,
                'message' => 'El programa no se pudo encontrar',
            ], 404);
        }else{

            $programa->medio;

            $programa->categorias = $programa->categorias()->get()->map(function($categoria){
                return $categoria->id;
            });;

            return response()->json([
                'ready' => true,
                'programa' => $programa,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Programa  $programa
     * @return \Illuminate\Http\Response
     */
    public function edit(Programa $programa)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Programa  $programa
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Programa $programa)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.unique' => 'Ya se encuentra registrado el programa deseado con el mismo nombre.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => [
                    'required',
                    Rule::unique('programas')->ignore($programa->id)->where(function ($query) use ($programa){
                        return $query->where('medio_id', $programa->medio_id)->whereNull('deleted_at');
                    }),
                ],
                'categorias' => ['nullable','array'],
                'categorias.*' => ['exists:categorias,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $programa->nombre = $request->nombre;
            if (!$programa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El programa no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $programa->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            $programa->periodicidad = isset($request->periodicidad) ? $request->periodicidad : null;
            if (!$programa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El programa no se ha actualizado',
                ], 500);
            }

            if (isset($request->categorias)) {

                $programa->categorias()->sync($request->categorias);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El programa se ha actualizado correctamente',
                'programa' => $programa,
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
     * @param  \App\Programa  $programa
     * @return \Illuminate\Http\Response
     */
    public function destroy(Programa $programa)
    {
        //
        try {
            DB::beginTransaction();

            $existsProgramaContacto = ProgramaContacto::where('programa_id', $programa->id)->exists();

            if($existsProgramaContacto){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El programa se encuentra relacionado con diferentes contactos.',
                ], 400);
            }

            ProgramaPlataforma::where('programa_id', $programa->id)->delete();

            if (!$programa->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El programa no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El programa se ha eliminado correctamente',
                'programa' => $programa,
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
        $programas = Programa::with('medio')->get()->filter(function ($programa) use ($idMedio){
            return $programa->medio_id == $idMedio;
        });

        return response()->json([
            'ready' => true,
            'programas' => $programas->values(),
        ]);
    }
}
