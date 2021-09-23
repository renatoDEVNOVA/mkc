<?php

namespace App\Http\Controllers;

use App\Etiqueta;
use App\Http\Requests\EtiquetaRequest;
use App\Http\Resources\EtiquetaCollection;
use DB;
use Illuminate\Http\Request;

class EtiquetaMaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = request('search', );
        if ($query !== 'null' && trim($query) !== '') {
            $etiqueta = Etiqueta::search($query)->orderBy('slug', 'asc');
        } else {
            $etiqueta = Etiqueta::orderBy('slug', 'asc');
        }
        $etiquetas = new EtiquetaCollection($etiqueta->get());
        return $etiquetas;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(EtiquetaRequest $request)
    {
        try {
            DB::beginTransaction();

            $etiqueta = new Etiqueta;

            $etiqueta->slug = $request->slug;

            if (!$etiqueta->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La etiqueta no se ha guardado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La etiqueta se ha guardado correctamente',
                'etiqueta' => $etiqueta,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
                'message' => 'Error al guardar etiqueta',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $etiqueta = Etiqueta::find($id);
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function update(EtiquetaRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $etiqueta = Etiqueta::find($id);

            $etiqueta->slug = $request->slug;

            if (!$etiqueta->update()) {
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
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
                'message' => 'Error al actualizar etiqueta',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Etiqueta  $etiqueta
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        try {
            DB::beginTransaction();

            $etiqueta = Etiqueta::find($id);

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
