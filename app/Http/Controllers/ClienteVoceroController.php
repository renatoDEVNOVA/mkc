<?php

namespace App\Http\Controllers;

use App\ClienteVocero;
use App\CampaignVocero;
use Illuminate\Http\Request;

use App\Persona;
use Validator;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class ClienteVoceroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $clienteVoceros = ClienteVocero::all();

        return response()->json([
            'ready' => true,
            'clienteVoceros' => $clienteVoceros,
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
                'idVocero.required' => 'El vocero es obligatorio.',
                'idVocero.exists' => 'Seleccione un vocero valido.',
                'idVocero.unique' => 'Ya se encuentra asignada el vocero al cliente.',
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un cliente valido.',
                'cargo.required' => 'El cargo es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idVocero' => [
                    'required',
                    'exists:personas,id',
                    Rule::unique('cliente_voceros')->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'cliente_id' => ['required','exists:clientes,id'],
                'cargo' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $persona = Persona::find($request->idVocero);
            if(!$persona->isVocero()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un vocero',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'cliente_id' => $request->cliente_id,
                'idVocero' => $request->idVocero,
                'cargo' => $request->cargo,
            );

            $clienteVocero = ClienteVocero::create($data);

            if (!$clienteVocero->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El vocero no ha sido asignado al cliente',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El vocero ha sido asignado correctamente al cliente',
                'clienteVocero' => $clienteVocero,
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
     * @param  \App\ClienteVocero  $clienteVocero
     * @return \Illuminate\Http\Response
     */
    public function show(ClienteVocero $clienteVocero)
    {
        //
        if(is_null($clienteVocero)){
            return response()->json([
                'ready' => false,
                'message' => 'El registro no se pudo encontrar',
            ], 404);
        }else{

            $clienteVocero->cliente;
            $clienteVocero->vocero;

            return response()->json([
                'ready' => true,
                'clienteVocero' => $clienteVocero,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ClienteVocero  $clienteVocero
     * @return \Illuminate\Http\Response
     */
    public function edit(ClienteVocero $clienteVocero)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ClienteVocero  $clienteVocero
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ClienteVocero $clienteVocero)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'cargo.required' => 'El cargo es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'cargo' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            /*$persona = Persona::find($clienteVocero->idVocero);
            if(!$persona->isVocero()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un vocero',
                ], 400);
            }*/

            // Datos Obligatorios
            $clienteVocero->cargo = $request->cargo;
            if (!$clienteVocero->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha actualizado correctamente',
                'clienteVocero' => $clienteVocero,
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
     * @param  \App\ClienteVocero  $clienteVocero
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClienteVocero $clienteVocero)
    {
        //
        try {
            DB::beginTransaction();

            $existsCampaignVocero = CampaignVocero::where('idVocero', $clienteVocero->idVocero)->whereHas('campaign', function (Builder $query) use ($clienteVocero){
                $query->where('cliente_id', $clienteVocero->cliente_id);
            })->exists();

            if($existsCampaignVocero){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El vocero se encuentra relacionado con diferentes campañas del cliente.',
                ], 400);
            }

            if (!$clienteVocero->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha eliminado correctamente',
                'clienteVocero' => $clienteVocero,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByCliente($idCliente)
    {
        $clienteVoceros = ClienteVocero::with('cliente','vocero')->get()->filter(function ($clienteVocero) use ($idCliente){
            return $clienteVocero->cliente_id == $idCliente && $clienteVocero->vocero->isVocero();
        });

        return response()->json([
            'ready' => true,
            'clienteVoceros' => $clienteVoceros->values(),
        ]);
    }

    public function getListByVocero($idVocero)
    {
        $clienteVoceros = ClienteVocero::with('cliente','vocero')->get()->filter(function ($clienteVocero) use ($idVocero){
            return $clienteVocero->idVocero == $idVocero && $clienteVocero->vocero->isVocero();
        });

        return response()->json([
            'ready' => true,
            'clienteVoceros' => $clienteVoceros->values(),
        ]);
    }
}
