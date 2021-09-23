<?php

namespace App\Http\Controllers;

use App\Categoria;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categorias = Categoria::with(
            'categoriaPadre',
        )->get();

        return response()->json([
            'ready' => true,
            'categorias' => $categorias,
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
                'descripcion.unique' => 'Ya se encuentra registrada una categoria con la misma descipcion.',
                'idCategoriaPadre.exists' => 'Seleccione una Categoria Principal valida.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:categorias,descripcion,NULL,id,deleted_at,NULL'],
                'idCategoriaPadre' => ['nullable','exists:categorias,id'],
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

            $categoria = Categoria::create($data);

            if (!$categoria->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La categoria no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $categoria->idCategoriaPadre = isset($request->idCategoriaPadre) ? $request->idCategoriaPadre : null;
            if (!$categoria->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La categoria no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La categoria se ha creado correctamente',
                'categoria' => $categoria,
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
     * @param  \App\Categoria  $categoria
     * @return \Illuminate\Http\Response
     */
    public function show(Categoria $categoria)
    {
        //
        if(is_null($categoria)){
            return response()->json([
                'ready' => false,
                'message' => 'Categoria no encontrada',
            ], 404);
        }else{

            $categoria->categoriaPadre;

            return response()->json([
                'ready' => true,
                'categoria' => $categoria,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Categoria  $categoria
     * @return \Illuminate\Http\Response
     */
    public function edit(Categoria $categoria)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Categoria  $categoria
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Categoria $categoria)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'descripcion.required' => 'La descripcion es obligatorio.',
                'descripcion.unique' => 'Ya se encuentra registrada una categoria con la misma descipcion.',
                'idCategoriaPadre.exists' => 'Seleccione una Categoria Principal valida.',
            ];

            $validator = Validator::make($request->all(), [
                'descripcion' => ['required', 'unique:categorias,descripcion,' . $categoria->id . ',id,deleted_at,NULL'],
                'idCategoriaPadre' => [
                    'nullable',
                    Rule::exists('categorias','id')->where(function ($query) use ($categoria){
                        $query->where('id', '!=', $categoria->id);
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

            // Datos Obligatorios
            $categoria->descripcion = $request->descripcion;
            if (!$categoria->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La categoria no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $categoria->idCategoriaPadre = isset($request->idCategoriaPadre) ? $request->idCategoriaPadre : null;
            if (!$categoria->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La categoria no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La categoria se ha actualizado correctamente',
                'categoria' => $categoria,
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
     * @param  \App\Categoria  $categoria
     * @return \Illuminate\Http\Response
     */
    public function destroy(Categoria $categoria)
    {
        //
        try {
            DB::beginTransaction();

            $countPersonas = $categoria->personas()->count();

            if($countPersonas > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La categoria se encuentra relacionada con diferentes personas.',
                ], 400);
            }

            $countProgramas = $categoria->programas()->count();

            if($countProgramas > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La categoria se encuentra relacionada con diferentes programas.',
                ], 400);
            }

            if (!$categoria->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La categoria no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La categoria se ha eliminado correctamente',
                'categoria' => $categoria,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
