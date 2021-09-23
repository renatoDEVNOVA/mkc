<?php

namespace App\Http\Controllers;

use App\Registro;
use Illuminate\Http\Request;

use App\PlanMedio;
use Illuminate\Validation\Rule;
use Validator;
use DB;

class RegistroController extends Controller
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
                'idPlanMedio.required' => 'El Plan de Medios es obligatorio.',
                'idPlanMedio.exists' => 'Seleccione un Plan de Medios valido.',
                'status.required' => 'Estado es obligatorio.',
                'status.in' => 'Selecione un Estado valido.',
                'observacion.required' => 'Observacion es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'status' => [
                    'required',
                    Rule::in([0, 1, 2]),
                ],
                'observacion' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();
            $planMedio = PlanMedio::find($request->idPlanMedio);

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el estado del plan de medios deseado',
                ], 400);
            }

            $lastRegistro = Registro::where('idPlanMedio', $request->idPlanMedio)->orderBy('created_at', 'desc')->first();

            if(!is_null($lastRegistro) && $request->status < $lastRegistro->status){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de pasar a un estado anterior al actual',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'idPlanMedio' => $request->idPlanMedio,
                'status' => $request->status,
                'observacion' => $request->observacion,
                'user_id' => auth()->user()->id,
            );

            $registro = Registro::create($data);

            if (!$registro->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha creado',
                ], 500);
            }

            // Actualizamos el estado del PM
            $planMedio = PlanMedio::find($request->idPlanMedio);
            $planMedio->status = $request->status;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar actualizar el estado del PM',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha creado correctamente',
                'registro' => $registro,
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
     * @param  \App\Registro  $registro
     * @return \Illuminate\Http\Response
     */
    public function show(Registro $registro)
    {
        //
        if(is_null($registro)){
            return response()->json([
                'ready' => false,
                'message' => 'Registro no encontrada',
            ], 404);
        }else{

            $registro->user;
            $registro->planMedio;

            return response()->json([
                'ready' => true,
                'registro' => $registro,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Registro  $registro
     * @return \Illuminate\Http\Response
     */
    public function edit(Registro $registro)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Registro  $registro
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Registro $registro)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Registro  $registro
     * @return \Illuminate\Http\Response
     */
    public function destroy(Registro $registro)
    {
        //
    }
}
