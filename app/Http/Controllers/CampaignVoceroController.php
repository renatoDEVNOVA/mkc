<?php

namespace App\Http\Controllers;

use App\CampaignVocero;
use Illuminate\Http\Request;

use App\Persona;
use App\Campaign;
use App\DetallePlanMedio;
use Validator;
use DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class CampaignVoceroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $campaignVoceros = CampaignVocero::all();

        return response()->json([
            'ready' => true,
            'campaignVoceros' => $campaignVoceros,
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
                'idVocero.unique' => 'Ya se encuentra asignado el vocero a la campaña deseada.',
                'campaign_id.required' => 'La campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una campaña valida.',
            ];

            $validator = Validator::make($request->all(), [
                'campaign_id' => ['required','exists:campaigns,id'],
                'idVocero' => [
                    'required',
                    Rule::exists('cliente_voceros','idVocero')->where(function ($query) use ($request){
                        $campaign = Campaign::find($request->campaign_id);
                        $query->where('cliente_id', $campaign->cliente_id);
                    }),
                    Rule::unique('campaign_voceros')->where(function ($query) use ($request){
                        return $query->where('campaign_id', $request->campaign_id)->whereNull('deleted_at');
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

            $persona = Persona::find($request->idVocero);
            if(!$persona->isVocero()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un vocero',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'campaign_id' => $request->campaign_id,
                'idVocero' => $request->idVocero,
            );

            $campaignVocero = CampaignVocero::create($data);

            if (!$campaignVocero->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El vocero no ha sido asignado a la campaña',
                ], 500);
            }

            // Datos Opcionales
            $campaignVocero->vinculo = isset($request->vinculo) ? $request->vinculo : null;
            $campaignVocero->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$campaignVocero->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El vocero no ha sido asignado a la campaña',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El vocero ha sido asignado correctamente a la campaña',
                'campaignVocero' => $campaignVocero,
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
     * @param  \App\CampaignVocero  $campaignVocero
     * @return \Illuminate\Http\Response
     */
    public function show(CampaignVocero $campaignVocero)
    {
        //
        if(is_null($campaignVocero)){
            return response()->json([
                'ready' => false,
                'message' => 'El registro no se pudo encontrar',
            ], 404);
        }else{

            $campaignVocero->campaign;
            $campaignVocero->vocero;

            return response()->json([
                'ready' => true,
                'campaignVocero' => $campaignVocero,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CampaignVocero  $campaignVocero
     * @return \Illuminate\Http\Response
     */
    public function edit(CampaignVocero $campaignVocero)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CampaignVocero  $campaignVocero
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CampaignVocero $campaignVocero)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idVocero.required' => 'El vocero es obligatorio.',
                'idVocero.exists' => 'Seleccione un vocero valido.',
                'idVocero.unique' => 'Ya se encuentra asignado el vocero a la campaña deseada.',
                'campaign_id.required' => 'La campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una campaña valida.',
            ];

            $validator = Validator::make($request->all(), [
                /*'campaign_id' => ['required','exists:campaigns,id'],
                'idVocero' => [
                    'required',
                    Rule::exists('cliente_voceros','idVocero')->where(function ($query) use ($request){
                        $campaign = Campaign::find($request->campaign_id);
                        $query->where('cliente_id', $campaign->cliente_id);
                    }),
                    Rule::unique('campaign_voceros')->ignore($campaignVocero->id)->where(function ($query) use ($request){
                        return $query->where('campaign_id', $request->campaign_id)->whereNull('deleted_at');
                    }),
                ],*/
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            /*$persona = Persona::find($request->idVocero);
            if(!$persona->isVocero()){
                return response()->json([
                    'ready' => false,
                    'message' => 'La persona seleccionada no es un vocero',
                ], 400);
            }*7

            // Datos Obligatorios
            /*$campaignVocero->campaign_id = $request->campaign_id;
            $campaignVocero->idVocero = $request->idVocero;
            if (!$campaignVocero->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha actualizado',
                ], 500);
            }*/

            // Datos Opcionales
            $campaignVocero->vinculo = isset($request->vinculo) ? $request->vinculo : null;
            $campaignVocero->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$campaignVocero->save()) {
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
                'campaignVocero' => $campaignVocero,
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
     * @param  \App\CampaignVocero  $campaignVocero
     * @return \Illuminate\Http\Response
     */
    public function destroy(CampaignVocero $campaignVocero)
    {
        //
        try {
            DB::beginTransaction();

            $existsDPMV = DetallePlanMedio::whereHas('planMedio', function (Builder $query) use ($campaignVocero){
                $query->where('campaign_id', $campaignVocero->campaign_id);
            })->whereHas('voceros', function (Builder $query) use ($campaignVocero){
                $query->where('personas.id', $campaignVocero->idVocero);
            })->exists();

            if($existsDPMV){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El vocero se encuentra relacionado con diferentes planes de medios de la campaña.',
                ], 400);
            }

            if (!$campaignVocero->delete()) {
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
                'campaignVocero' => $campaignVocero,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByCampaign($idCampaign)
    {
        $campaignVoceros = CampaignVocero::with('campaign','vocero')->where('campaign_id', $idCampaign)->get();

        return response()->json([
            'ready' => true,
            'campaignVoceros' => $campaignVoceros->values(),
        ]);
    }
}
