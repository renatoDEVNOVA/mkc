<?php

namespace App\Http\Controllers;

use App\Bitacora;
use Illuminate\Http\Request;

use App\Atributo;
use App\DetallePlanMedio;
use App\PlanMedio;
use Illuminate\Validation\Rule;
use Validator;
use DB;

class BitacoraController extends Controller
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
                'idDetallePlanMedio.required' => 'El Detalle de Plan de Medio es obligatoria.',
                'idDetallePlanMedio.exists' => 'Seleccione una Detalle de Plan de Medio valida.',
                'estado.required' => 'Estado es obligatorio.',
                'estado.in' => 'Selecione un Estado valido.',
                'observacion.required' => 'Observacion es obligatorio.',
                'idTipoComunicacion.required' => 'El Tipo de Comunicacion es obligatorio.',
                'idTipoComunicacion.exists' => 'Seleccione un Tipo de Comunicacion valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idDetallePlanMedio' => ['required','exists:detalle_plan_medios,id'],
                'estado' => [
                    'required',
                    Rule::in([1, 2, 3, 4, 5]),
                ],
                'observacion' => ['required'],
                'idTipoComunicacion' => [
                    'required',
                    Rule::exists('tipo_atributos','id')->where(function ($query) {
                        $atributo = Atributo::where('slug', 'comunicacion')->first();
                        $query->where('atributo_id', $atributo->id);
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

            $idsCampaign = auth()->user()->myCampaigns();
            $detallePlanMedio = DetallePlanMedio::find($request->idDetallePlanMedio);
            $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign) && $detallePlanMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para transferir el DPM',
                ], 400);
            }

            $lastBitacora = Bitacora::where('idDetallePlanMedio', $request->idDetallePlanMedio)->orderBy('created_at', 'desc')->first();

            if($request->estado < $lastBitacora->estado){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de pasar a un estado anterior al actual',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'idDetallePlanMedio' => $request->idDetallePlanMedio,
                'tipoBitacora' => 1,
                'estado' => $request->estado,
                'observacion' => $request->observacion,
                'idTipoComunicacion' => $request->idTipoComunicacion,
                'user_id' => auth()->user()->id,
                'idUserExtra' => null,
            );

            $bitacora = Bitacora::create($data);

            if (!$bitacora->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La bitacora no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La bitacora se ha creado correctamente',
                'bitacora' => $bitacora,
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
     * @param  \App\Bitacora  $bitacora
     * @return \Illuminate\Http\Response
     */
    public function show(Bitacora $bitacora)
    {
        //
        if(is_null($bitacora)){
            return response()->json([
                'ready' => false,
                'message' => 'Bitacora no encontrada',
            ], 404);
        }else{

            $bitacora->user;
            $bitacora->tipoComunicacion;

            return response()->json([
                'ready' => true,
                'bitacora' => $bitacora,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Bitacora  $bitacora
     * @return \Illuminate\Http\Response
     */
    public function edit(Bitacora $bitacora)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Bitacora  $bitacora
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Bitacora $bitacora)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Bitacora  $bitacora
     * @return \Illuminate\Http\Response
     */
    public function destroy(Bitacora $bitacora)
    {
        //
    }
}
