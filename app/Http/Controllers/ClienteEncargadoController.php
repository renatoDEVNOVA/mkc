<?php

namespace App\Http\Controllers;

use App\ClienteEncargado;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class ClienteEncargadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $clienteEncargados = ClienteEncargado::all();

        return response()->json([
            'ready' => true,
            'clienteEncargados' => $clienteEncargados,
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
                'idEncargado.required' => 'La persona es obligatoria.',
                'idEncargado.exists' => 'Seleccione una persona valida.',
                'idEncargado.unique' => 'Ya se encuentra asignada la persona al cliente.',
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un cliente valido.',
                'tipoEncargado.required' => 'El cargo es obligatorio.',
                'tipoEncargado.integer' => 'Seleccione un cargo valida.',
            ];

            $validator = Validator::make($request->all(), [
                'idEncargado' => [
                    'required',
                    'exists:personas,id',
                    Rule::unique('cliente_encargados')->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'cliente_id' => ['required','exists:clientes,id'],
                'tipoEncargado' => ['required','integer'],
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
                'cliente_id' => $request->cliente_id,
                'idEncargado' => $request->idEncargado,
                'tipoEncargado' => $request->tipoEncargado,
            );

            $clienteEncargado = ClienteEncargado::create($data);

            if (!$clienteEncargado->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona no ha sido asignada al cliente',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La persona ha sido asignada correctamente al cliente',
                'clienteEncargado' => $clienteEncargado,
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
     * @param  \App\ClienteEncargado  $clienteEncargado
     * @return \Illuminate\Http\Response
     */
    public function show(ClienteEncargado $clienteEncargado)
    {
        //
        if(is_null($clienteEncargado)){
            return response()->json([
                'ready' => false,
                'message' => 'El registro no se pudo encontrar',
            ], 404);
        }else{

            $clienteEncargado->cliente;
            $clienteEncargado->encargado;

            return response()->json([
                'ready' => true,
                'clienteEncargado' => $clienteEncargado,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ClienteEncargado  $clienteEncargado
     * @return \Illuminate\Http\Response
     */
    public function edit(ClienteEncargado $clienteEncargado)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ClienteEncargado  $clienteEncargado
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ClienteEncargado $clienteEncargado)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idEncargado.required' => 'La persona es obligatoria.',
                'idEncargado.exists' => 'Seleccione una persona valida.',
                'idEncargado.unique' => 'Ya se encuentra asignada la persona al cliente.',
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un cliente valido.',
                'tipoEncargado.required' => 'El cargo es obligatorio.',
                'tipoEncargado.integer' => 'Seleccione un cargo valida.',
            ];

            $validator = Validator::make($request->all(), [
                /*'idEncargado' => [
                    'required',
                    'exists:personas,id',
                    Rule::unique('cliente_encargados')->ignore($clienteEncargado->id)->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'cliente_id' => ['required','exists:clientes,id'],*/
                'tipoEncargado' => ['required','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            //$clienteEncargado->cliente_id = $request->cliente_id;
            //$clienteEncargado->idEncargado = $request->idEncargado;
            $clienteEncargado->tipoEncargado = $request->tipoEncargado;
            if (!$clienteEncargado->save()) {
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
                'clienteEncargado' => $clienteEncargado,
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
     * @param  \App\ClienteEncargado  $clienteEncargado
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClienteEncargado $clienteEncargado)
    {
        //
        try {
            DB::beginTransaction();

            if (!$clienteEncargado->delete()) {
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
                'clienteEncargado' => $clienteEncargado,
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
        $clienteEncargados = ClienteEncargado::with('cliente','encargado.emails.tipoEmail','encargado.telefonos.tipoTelefono')->get()->filter(function ($clienteEncargado) use ($idCliente){
            return $clienteEncargado->cliente_id == $idCliente;
        });

        return response()->json([
            'ready' => true,
            'clienteEncargados' => $clienteEncargados->values(),
        ]);
    }
}
