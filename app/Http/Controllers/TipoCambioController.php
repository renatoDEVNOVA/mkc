<?php

namespace App\Http\Controllers;

use App\TipoCambio;
use Illuminate\Http\Request;

use App\ResultadoPlataforma;
use Validator;
use DB;

class TipoCambioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tipoCambios = TipoCambio::with('user')->get();

        return response()->json([
            'ready' => true,
            'tipoCambios' => $tipoCambios,
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
                'TC.required' => 'El TC es obligatorio.',
                'TC.numeric' => 'Ingrese un TC valido.',
            ];

            $validator = Validator::make($request->all(), [
                'TC' => ['required', 'numeric'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $data = array(
                'TC' => $request->TC,
                'user_id' => auth()->user()->id,
            );

            $tipoCambio = TipoCambio::create($data);

            if (!$tipoCambio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de cambio no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de cambio se ha creado correctamente',
                'tipoCambio' => $tipoCambio,
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
     * @param  \App\TipoCambio  $tipoCambio
     * @return \Illuminate\Http\Response
     */
    public function show(TipoCambio $tipoCambio)
    {
        //
        if(is_null($tipoCambio)){
            return response()->json([
                'ready' => false,
                'message' => 'El tipo de cambio no se pudo encontrar',
            ], 404);
        }else{

            $tipoCambio->user;

            return response()->json([
                'ready' => true,
                'tipoCambio' => $tipoCambio,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TipoCambio  $tipoCambio
     * @return \Illuminate\Http\Response
     */
    public function edit(TipoCambio $tipoCambio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TipoCambio  $tipoCambio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TipoCambio $tipoCambio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'TC.required' => 'El TC es obligatorio.',
                'TC.numeric' => 'Ingrese un TC valido.',
            ];

            $validator = Validator::make($request->all(), [
                'TC' => ['required', 'numeric'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $tipoCambio->TC = $request->TC;
            if (!$tipoCambio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de cambio no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de cambio se ha actualizado correctamente',
                'tipoCambio' => $tipoCambio,
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
     * @param  \App\TipoCambio  $tipoCambio
     * @return \Illuminate\Http\Response
     */
    public function destroy(TipoCambio $tipoCambio)
    {
        //
        try {
            DB::beginTransaction();

            $existsResultadoPlataforma = ResultadoPlataforma::where('idTipoCambio', $tipoCambio->id)->exists();

            if($existsResultadoPlataforma){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El tipo de cambio se encuentra relacionado con diferentes publicaciones.',
                ], 400);
            }

            if (!$tipoCambio->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El tipo de cambio no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El tipo de cambio se ha eliminado correctamente',
                'tipoCambio' => $tipoCambio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getCurrent()
    {
        $tipoCambio = TipoCambio::orderBy('id', 'desc')->first();

        return response()->json([
            'ready' => true,
            'tipoCambio' => $tipoCambio,
        ]);

    }
}
