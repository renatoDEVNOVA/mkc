<?php

namespace App\Http\Controllers;

use App\Etiqueta;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class EtiquetaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $etiquetas = Etiqueta::orderBy('slug', 'asc')->get(); 
        
        return response()->json([
            'ready' => true,
            'etiquetas' => $etiquetas,
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
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function show(Etiqueta $etiqueta)
    {
        //
        if(is_null($etiqueta)){
            return response()->json([
                'ready' => false,
                'message' => 'Etiqueta no encontrada',
            ], 404);
        }else{

            return response()->json([
                'ready' => true,
                'etiqueta' => $etiqueta,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function edit(Etiqueta $etiqueta)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Etiqueta $etiqueta)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'slug.required' => 'La descripcion es obligatorio.',
                'slug.unique' => 'Ya se encuentra registrada una etiqueta con la misma descipcion.',
            ];

            $validator = Validator::make($request->all(), [
                'slug' => ['required', 'unique:etiquetas,slug,' . $etiqueta->id . ',id,deleted_at,NULL'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $etiqueta->slug = $request->slug;
            if (!$etiqueta->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La etiqueta no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La etiqueta se ha actualizado correctamente',
                'etiqueta' => $etiqueta,
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
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function destroy(Etiqueta $etiqueta)
    {
        //
        try {
            DB::beginTransaction();

            $countCampaigns = $etiqueta->campaigns()->count();

            if($countCampaigns > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La etiqueta se encuentra relacionada con diferentes campañas.',
                ], 400);
            }

            if (!$etiqueta->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La etiqueta no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La etiqueta se ha eliminado correctamente',
                'etiqueta' => $etiqueta,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
