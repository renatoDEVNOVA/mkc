<?php

namespace App\Http\Controllers;

use App\CampaignResponsable;
use Illuminate\Http\Request;

use App\PlanMedio;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class CampaignResponsableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $campaignResponsables = CampaignResponsable::all();

        return response()->json([
            'ready' => true,
            'campaignResponsables' => $campaignResponsables,
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
                'user_id.required' => 'El agente es obligatorio.',
                'user_id.exists' => 'Seleccione un agente valido.',
                'user_id.unique' => 'Ya se encuentra asignado el agente a la campaña deseada.',
                'campaign_id.required' => 'La campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una campaña valida.',
            ];

            $validator = Validator::make($request->all(), [
                'campaign_id' => ['required','exists:campaigns,id'],
                'user_id' => [
                    'required',
                    'exists:users,id',
                    Rule::unique('campaign_responsables')->where(function ($query) use ($request){
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

            // Datos Obligatorios
            $data = array(
                'campaign_id' => $request->campaign_id,
                'user_id' => $request->user_id,
            );

            $campaignResponsable = CampaignResponsable::create($data);

            if (!$campaignResponsable->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El agente no ha sido asignado a la campaña',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El agente ha sido asignado correctamente a la campaña',
                'campaignResponsable' => $campaignResponsable,
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
     * @param  \App\CampaignResponsable  $campaignResponsable
     * @return \Illuminate\Http\Response
     */
    public function show(CampaignResponsable $campaignResponsable)
    {
        //
        if(is_null($campaignResponsable)){
            return response()->json([
                'ready' => false,
                'message' => 'El registro no se pudo encontrar',
            ], 404);
        }else{

            $campaignResponsable->campaign;
            $campaignResponsable->user;

            return response()->json([
                'ready' => true,
                'campaignResponsable' => $campaignResponsable,
            ]);

        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CampaignResponsable  $campaignResponsable
     * @return \Illuminate\Http\Response
     */
    public function edit(CampaignResponsable $campaignResponsable)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CampaignResponsable  $campaignResponsable
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CampaignResponsable $campaignResponsable)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\CampaignResponsable  $campaignResponsable
     * @return \Illuminate\Http\Response
     */
    public function destroy(CampaignResponsable $campaignResponsable)
    {
        //
        try {
            DB::beginTransaction();

            $existsPlanMedio = PlanMedio::where('campaign_id', $campaignResponsable->campaign_id)->where('user_id', $campaignResponsable->user_id)->exists();

            if($existsPlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El agente se encuentra relacionado con diferentes planes de medios.',
                ], 400);
            }

            if (!$campaignResponsable->delete()) {
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
                'campaignResponsable' => $campaignResponsable,
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
        $campaignResponsables = CampaignResponsable::with('campaign','user')->where('campaign_id', $idCampaign)->get();

        return response()->json([
            'ready' => true,
            'campaignResponsables' => $campaignResponsables->values(),
        ]);
    }
}
