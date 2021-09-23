<?php

namespace App\Http\Controllers;

use App\ClienteEnvio;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

use Cron\CronExpression;

class ClienteEnvioController extends Controller
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
    public function storeBK(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un cliente valido.',
                'tipoPeriodo.required' => 'La frecuencia es obligatorio.',
                'tipoPeriodo.in' => 'Seleccione una frecuencia valida.',
                'tipoPeriodo.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
                'diaEnvio.required_if' => 'El Dia de Envio es obligatorio para la frecuencia Semanal.',
                'diaEnvio.integer' => 'Seleccione un Dia de Envio valido.',
                'horaEnvio.required_if' => 'El Dia de Envio es obligatorio para la frecuencia Semanal.',
                'horaEnvio.integer' => 'Seleccione un Dia de Envio valido.',
            ];

            $validator = Validator::make($request->all(), [
                'cliente_id' => ['required','exists:clientes,id'],
                'tipoPeriodo' => [
                    'required',
                    Rule::in([1, 2]),
                    Rule::unique('cliente_envios')->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'diaEnvio' => ['required_if:tipoPeriodo,1','integer'],
                'horaEnvio' => ['required','integer'],
                'tiposReporte' => ['required','array'],
                'tiposReporte.*' => [
                    Rule::in([1, 2, 3]),
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
            $data = array(
                'cliente_id' => $request->cliente_id,
                'tipoPeriodo' => $request->tipoPeriodo,
                'diaEnvio' => ($request->tipoPeriodo == 1) ? $request->diaEnvio : 1,
                'horaEnvio' => $request->horaEnvio,
                'tiposReporte' => implode(',', $request->tiposReporte),
            );

            $clienteEnvio = ClienteEnvio::create($data);

            if (!$clienteEnvio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El envio automatico no ha sido asignado al cliente',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El envio automatico ha sido asignado correctamente al cliente',
                'clienteEnvio' => $clienteEnvio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un cliente valido.',
                'cron.required' => 'La frecuencia es obligatoria.',
                'cron.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
                'tipoFrecuencia.required' => 'La frecuencia es obligatorio.',
                'tipoFrecuencia.in' => 'Seleccione una frecuencia valida.',
                'tipoFrecuencia.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
            ];

            $validator = Validator::make($request->all(), [
                'cliente_id' => ['required','exists:clientes,id'],
                'cron' => [
                    'required',
                    Rule::unique('cliente_envios')->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'tiposReporte' => ['required','array'],
                'tiposReporte.*' => [
                    Rule::in([1, 2, 3]),
                ],
                'tipoFrecuencia' => [
                    'required',
                    Rule::in([1, 2, 3]),
                    Rule::unique('cliente_envios')->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                //'periodoActual' => ['required','boolean'],
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
                'cron' => $request->cron,
                'tiposReporte' => implode(',', $request->tiposReporte),
                'tipoFrecuencia' => $request->tipoFrecuencia,
                //'periodoActual' => $request->periodoActual,
            );

            $clienteEnvio = ClienteEnvio::create($data);

            if (!$clienteEnvio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El envio automatico no ha sido asignado al cliente',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El envio automatico ha sido asignado correctamente al cliente',
                'clienteEnvio' => $clienteEnvio,
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
     * @param  \App\ClienteEnvio  $clienteEnvio
     * @return \Illuminate\Http\Response
     */
    public function show(ClienteEnvio $clienteEnvio)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ClienteEnvio  $clienteEnvio
     * @return \Illuminate\Http\Response
     */
    public function edit(ClienteEnvio $clienteEnvio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ClienteEnvio  $clienteEnvio
     * @return \Illuminate\Http\Response
     */
    public function updateBK(Request $request, ClienteEnvio $clienteEnvio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'tipoPeriodo.required' => 'La frecuencia es obligatorio.',
                'tipoPeriodo.in' => 'Seleccione una frecuencia valida.',
                'tipoPeriodo.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
                'diaEnvio.required_if' => 'El Dia de Envio es obligatorio para la frecuencia Semanal.',
                'diaEnvio.integer' => 'Seleccione un Dia de Envio valido.',
                'horaEnvio.required_if' => 'El Dia de Envio es obligatorio para la frecuencia Semanal.',
                'horaEnvio.integer' => 'Seleccione un Dia de Envio valido.',
            ];

            $validator = Validator::make($request->all(), [
                'tipoPeriodo' => [
                    'required',
                    Rule::in([1, 2]),
                    Rule::unique('cliente_envios')->ignore($clienteEnvio->id)->where(function ($query) use ($clienteEnvio){
                        return $query->where('cliente_id', $clienteEnvio->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'diaEnvio' => ['required_if:tipoPeriodo,1','integer'],
                'horaEnvio' => ['required','integer'],
                'tiposReporte' => ['required','array'],
                'tiposReporte.*' => [
                    Rule::in([1, 2, 3]),
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
            $clienteEnvio->tipoPeriodo = $request->tipoPeriodo;
            $clienteEnvio->diaEnvio = ($request->tipoPeriodo == 1) ? $request->diaEnvio : 1;
            $clienteEnvio->horaEnvio = $request->horaEnvio;
            $clienteEnvio->tiposReporte = implode(',', $request->tiposReporte);
            if (!$clienteEnvio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El envio automatico no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El envio automatico se ha actualizado correctamente',
                'clienteEnvio' => $clienteEnvio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function update(Request $request, ClienteEnvio $clienteEnvio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'cron.required' => 'La frecuencia es obligatoria.',
                'cron.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
                'tipoFrecuencia.required' => 'La frecuencia es obligatorio.',
                'tipoFrecuencia.in' => 'Seleccione una frecuencia valida.',
                'tipoFrecuencia.unique' => 'Ya se encuentra registrado un envio automatico con la frecuencia deseada.',
            ];

            $validator = Validator::make($request->all(), [
                'cron' => [
                    'required',
                    Rule::unique('cliente_envios')->ignore($clienteEnvio->id)->where(function ($query) use ($request){
                        return $query->where('cliente_id', $request->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                'tiposReporte' => ['required','array'],
                'tiposReporte.*' => [
                    Rule::in([1, 2, 3]),
                ],
                'tipoFrecuencia' => [
                    'required',
                    Rule::in([1, 2, 3]),
                    Rule::unique('cliente_envios')->ignore($clienteEnvio->id)->where(function ($query) use ($clienteEnvio){
                        return $query->where('cliente_id', $clienteEnvio->cliente_id)->whereNull('deleted_at');
                    }),
                ],
                //'periodoActual' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $clienteEnvio->cron = $request->cron;
            $clienteEnvio->tiposReporte = implode(',', $request->tiposReporte);
            $clienteEnvio->tipoFrecuencia = $request->tipoFrecuencia;
            //$clienteEnvio->periodoActual = $request->periodoActual;
            if (!$clienteEnvio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El envio automatico no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El envio automatico se ha actualizado correctamente',
                'clienteEnvio' => $clienteEnvio,
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
     * @param  \App\ClienteEnvio  $clienteEnvio
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClienteEnvio $clienteEnvio)
    {
        //
        try {
            DB::beginTransaction();

            if (!$clienteEnvio->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El envio automatico no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El envio automatico se ha eliminado correctamente',
                'clienteEnvio' => $clienteEnvio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function nextSendAndDateRange(Request $request)
    {
        
        try {

            $messages = [
                'cron.required' => 'La frecuencia es obligatoria.',
                'tipoFrecuencia.required' => 'La frecuencia es obligatorio.',
                'tipoFrecuencia.in' => 'Seleccione una frecuencia valida.',
            ];

            $validator = Validator::make($request->all(), [
                'cron' => [
                    'required',
                ],
                'tipoFrecuencia' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                //'periodoActual' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $cron = new CronExpression($request->cron);
            $nextSend = $cron->getNextRunDate()->format('Y-m-d H:i');

            switch ($request->tipoFrecuencia) {
                case 1:
                    # Semanal
                    $date = strtotime($nextSend."- 1 week");
                    $W = date('W', $date);
                    $Y = date('o', $date);
                    $first = strtotime($Y.'-W'.$W.'-1');
                    $last = strtotime($Y.'-W'.$W.'-7');

                    $fechaInicio = date('Y-m-d', $first);
                    $fechaFin = date('Y-m-d', $last);
                    break;

                case 2:
                    # Mensual
                    $date = strtotime($nextSend);
                    $first = strtotime('first day of last month', $date);

                    $fechaInicio = date('Y-m-01', $first);
                    $fechaFin = date('Y-m-t', $first);
                    break;

                case 3:
                    # Quincenal
                    $date = strtotime($nextSend);

                    $fechaInicio = date('Y-m-01', $date);
                    $fechaFin = date('Y-m-15', $date);
                    break;

                default:
                    # code...
                    break;
            }

            /*if($request->tipoFrecuencia == 1){

                if($request->periodoActual){
                    $date = strtotime($nextSend);
                    $W = date('W', $date);
                    $Y = date('o', $date);
                    $first = strtotime($Y.'-W'.$W.'-1');

                    $fechaInicio = date('Y-m-d', $first);
                    $fechaFin = date('Y-m-d', $date);
                }else{
                    $date = strtotime($nextSend."- 1 week");
                    $W = date('W', $date);
                    $Y = date('o', $date);
                    $first = strtotime($Y.'-W'.$W.'-1');
                    $last = strtotime($Y.'-W'.$W.'-7');

                    $fechaInicio = date('Y-m-d', $first);
                    $fechaFin = date('Y-m-d', $last);
                }

            }else{

                if($request->periodoActual){
                    $date = strtotime($nextSend);

                    $fechaInicio = date('Y-m-01', $date);
                    $fechaFin = date('Y-m-d', $date);
                }else{
                    //$date = strtotime($nextSend."- 1 month");
                    $date = strtotime($nextSend);
                    $first = strtotime('first day of last month', $date);

                    $fechaInicio = date('Y-m-01', $first);
                    $fechaFin = date('Y-m-t', $first);
                }

            }*/

            return response()->json([
                'ready' => true,
                'nextSend' => $nextSend,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }
}
