<?php

namespace App\Http\Controllers;

use App\Campaign;
use Illuminate\Http\Request;

use App\Cliente;
use App\Etiqueta;
use App\CampaignPlataforma;
use App\CampaignVocero;
use App\PlanMedio;
use App\DetallePlanMedio;
use App\DetallePlanResultadoPlataforma;
use App\Http\Resources\Campaign\CampaignCollection;
use Validator;
use DB;
use Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $campaigns = Campaign::with(
            'cliente',
            'campaignGroup',
            'agente',
            'campaignResponsables.user',
        )->get();

        return response()->json([
            'ready' => true,
            'campaigns' => $campaigns,
        ]);
    }

    public function campaignSelect(){
        $idsCampaign = auth()->user()->myCampaigns();
        $campaigns = Campaign::select('titulo','id','cliente_id')
        ->orderBy('id', 'desc');

        if(auth()->user()->hasAnyRole(['admin','super-admin'])){
            $campaigns = $campaigns->get();
        }else{
            $campaigns = $campaigns->whereIn('id', $idsCampaign)->get();
        }
        return response()->json([
            'ready' => true,
            'campaigns' => $campaigns,
        ]);
    }

    public function indexV2(Request $request)
    {
        $query = $request->get('search');
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $idsCampaign = auth()->user()->myCampaigns();

        if ($query !== 'null' && trim($query) !== '') {
            $campaign = Campaign::search($query, null, true, true)->with(
                'cliente',
                'campaignGroup',
                'campaignResponsables.user',
            )->orderBy('id', 'desc');
        } else {
            $campaign = Campaign::with(
                'cliente',
                'campaignGroup',
                'campaignResponsables.user',
            )->orderBy('id', 'desc');
        }
        //->orWhereBetween('fechaInicio', [request('fechaInicio', ), request('fechaFin', )])
        if ($desde !== 'null' && trim($desde) !== '') {
            $campaigns = $campaign
                            ->whereBetween('fechaInicio', [$desde, $hasta])
                            ->orWhereBetween('fechaFin', [$desde, $hasta]);
        }else{
            $campaigns = $campaign;
        }
        //solo muestra las campañas que pertecen al agente, si es admin o super admin muestra todo
        if(auth()->user()->hasAnyRole(['admin','super-admin'])){
            $campaignsAgente = $campaigns;
        }else{
            $campaignsAgente = $campaigns->whereIn('id', $idsCampaign);
        }

        return new CampaignCollection($campaignsAgente->paginate(10));
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
                'titulo.required' => 'El Titulo es obligatorio.',
                'fechaInicio.required' => 'La Fecha de Inicio es obligatorio.',
                'fechaInicio.date' => 'Seleccione una Fecha de Inicio valida.',
                'fechaFin.required' => 'La Fecha de Fin es obligatorio.',
                'fechaFin.date' => 'Seleccione una Fecha de Fin valida.',
                'idCampaignGroup.exists' => 'Seleccione un Grupo de Campañas valido.',
                'cliente_id.required' => 'El Cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un Cliente valido.',
                'tipoPublico.required' => 'El Publico es obligatorio.',
                'tipoPublico.integer' => 'Seleccione un Publico valido.',
                'tipoObjetivo.required' => 'El Objetivo es obligatorio.',
                'tipoObjetivo.integer' => 'Seleccione un Objetivo valido.',
                'tipoAudiencia.required' => 'La Audiencia es obligatorio.',
                'tipoAudiencia.integer' => 'Seleccione una Audiencia valido.',
            ];

            $validator = Validator::make($request->all(), [
                'titulo' => ['required'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'idCampaignGroup' => ['nullable','exists:campaign_groups,id'],
                'cliente_id' => ['required','exists:clientes,id'],
                'tipoPublico' => ['required','integer'],
                'tipoObjetivo' => ['required','integer'],
                'tipoAudiencia' => ['required','integer'],
                'interesPublico' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'novedad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'actualidad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                /*'pesoPublico' => ['required','integer'],
                'pesoObjetivo' => ['required','integer'],
                'pesoAudiencia' => ['required','integer'],
                'pesoInteresPublico' => ['required','integer'],
                'pesoNovedad' => ['required','integer'],
                'pesoActualidad' => ['required','integer'],
                'pesoAutoridadCliente' => ['required','integer'],
                'pesoMediaticoCliente' => ['required','integer'],
                'pesoAutoridadVoceros' => ['required','integer'],
                'pesoMediaticoVoceros' => ['required','integer'],*/
                'plataformas' => ['present','array'],
                'plataformas.*.plataforma_id' => ['required','exists:plataformas,id'],
                'plataformas.*.meta' => ['required','integer'],
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
                'titulo' => $request->titulo,
                'fechaInicio' => $request->fechaInicio,
                'fechaFin' => $request->fechaFin,
                'cliente_id' => $request->cliente_id,
                'idAgente' => auth()->user()->id,
                'tipoPublico' => $request->tipoPublico,
                'tipoObjetivo' => $request->tipoObjetivo,
                'tipoAudiencia' => $request->tipoAudiencia,
                'interesPublico' => $request->interesPublico,
                'novedad' => $request->novedad,
                'actualidad' => $request->actualidad,
                'autoridadCliente' => $request->autoridadCliente,
                'mediaticoCliente' => $request->mediaticoCliente,
                'autoridadVoceros' => $request->autoridadVoceros,
                'mediaticoVoceros' => $request->mediaticoVoceros,
                /*'pesoPublico' => $request->pesoPublico,
                'pesoObjetivo' => $request->pesoObjetivo,
                'pesoAudiencia' => $request->pesoAudiencia,
                'pesoInteresPublico' => $request->pesoInteresPublico,
                'pesoNovedad' => $request->pesoNovedad,
                'pesoActualidad' => $request->pesoActualidad,
                'pesoAutoridadCliente' => $request->pesoAutoridadCliente,
                'pesoMediaticoCliente' => $request->pesoMediaticoCliente,
                'pesoAutoridadVoceros' => $request->pesoAutoridadVoceros,
                'pesoMediaticoVoceros' => $request->pesoMediaticoVoceros,*/
            );

            $campaign = Campaign::create($data);

            if (!$campaign->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha creado',
                ], 500);
            }
            
            $campaign->alias = 'cmp' . $campaign->id . '_' . Str::random(8);
            if (!$campaign->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha creado',
                ], 500);
            }

            // Crear directorio de la campaña
            $cliente = Cliente::find($campaign->cliente_id);

            Storage::makeDirectory('clientes/'.$cliente->alias.'/'.$campaign->alias);


            // Datos Opcionales
            $campaign->idCampaignGroup = isset($request->idCampaignGroup) ? $request->idCampaignGroup : null;
            $campaign->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$campaign->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha creado',
                ], 500);
            }

            if (isset($request->plataformas)) {
          
                foreach ($request->plataformas as $plataforma) {
                    # code...
                    $campaignPlataforma = CampaignPlataforma::updateOrCreate(
                        ['campaign_id' => $campaign->id, 'plataforma_id' => $plataforma['plataforma_id']],
                        ['meta' => $plataforma['meta']]
                    );
                }
      
            }

            if (isset($request->etiquetas)) {
          
                $idsEtiqueta = array();
                foreach ($request->etiquetas as $slug) {
                    # code...
                    $etiqueta = Etiqueta::firstOrCreate(['slug' => $slug]);
                    array_push($idsEtiqueta, $etiqueta->id);
                }
        
                $campaign->etiquetas()->sync($idsEtiqueta);
      
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La campaña se ha creado correctamente',
                'campaign' => $campaign,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    private function countPlataformas($idCampaign)
    {
        $plataformasCantidad = DetallePlanResultadoPlataforma::selectRaw("plataformas.id, COUNT(*) as cantidadActual")
            ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
            ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
            ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
            ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
            ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->whereNull('resultado_plataformas.deleted_at')
            ->whereNull('detalle_plan_medios.deleted_at')
            ->where('plan_medios.campaign_id', $idCampaign)
            ->groupBy('plataformas.id')
            ->get();
    
        return $plataformasCantidad;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function show(Campaign $campaign)
    {
        //
        if(is_null($campaign)){
            return response()->json([
                'ready' => false,
                'message' => 'Campaña no encontrada',
            ], 404);
        }else{

            $idsCampaign = auth()->user()->myCampaigns();

            $campaign->cliente;
            $campaign->campaignGroup;
            $campaign->agente;

            $campaign->etiquetas;

            $campaign->campaignPlataformas;
            $campaign->plataformasCantidad = $this->countPlataformas($campaign->id);

            $campaign->isEditable = in_array($campaign->id, $idsCampaign);

            return response()->json([
                'ready' => true,
                'campaign' => $campaign,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function edit(Campaign $campaign)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Campaign $campaign)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'titulo.required' => 'El Titulo es obligatorio.',
                'fechaInicio.required' => 'La Fecha de Inicio es obligatorio.',
                'fechaInicio.date' => 'Seleccione una Fecha de Inicio valida.',
                'fechaFin.required' => 'La Fecha de Fin es obligatorio.',
                'fechaFin.date' => 'Seleccione una Fecha de Fin valida.',
                'idCampaignGroup.exists' => 'Seleccione un Grupo de Campañas valido.',
                'cliente_id.required' => 'El Cliente es obligatorio.',
                'cliente_id.exists' => 'Seleccione un Cliente valido.',
                'tipoPublico.required' => 'El Publico es obligatorio.',
                'tipoPublico.integer' => 'Seleccione un Publico valido.',
                'tipoObjetivo.required' => 'El Objetivo es obligatorio.',
                'tipoObjetivo.integer' => 'Seleccione un Objetivo valido.',
                'tipoAudiencia.required' => 'La Audiencia es obligatorio.',
                'tipoAudiencia.integer' => 'Seleccione una Audiencia valido.',
            ];

            $validator = Validator::make($request->all(), [
                'titulo' => ['required'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'idCampaignGroup' => ['nullable','exists:campaign_groups,id'],
                //'cliente_id' => ['required','exists:clientes,id'],
                'tipoPublico' => ['required','integer'],
                'tipoObjetivo' => ['required','integer'],
                'tipoAudiencia' => ['required','integer'],
                'interesPublico' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'novedad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'actualidad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                /*'pesoPublico' => ['required','integer'],
                'pesoObjetivo' => ['required','integer'],
                'pesoAudiencia' => ['required','integer'],
                'pesoInteresPublico' => ['required','integer'],
                'pesoNovedad' => ['required','integer'],
                'pesoActualidad' => ['required','integer'],
                'pesoAutoridadCliente' => ['required','integer'],
                'pesoMediaticoCliente' => ['required','integer'],
                'pesoAutoridadVoceros' => ['required','integer'],
                'pesoMediaticoVoceros' => ['required','integer'],*/
                'plataformas' => ['present','array'],
                'plataformas.*.plataforma_id' => ['required','exists:plataformas,id'],
                'plataformas.*.meta' => ['required','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($campaign->id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar la campaña',
                ], 400);
            }

            //$oldCliente = Cliente::find($campaign->cliente_id);

            // Datos Obligatorios
            $campaign->titulo = $request->titulo;
            $campaign->fechaInicio = $request->fechaInicio;
            $campaign->fechaFin = $request->fechaFin;
            //$campaign->cliente_id = $request->cliente_id;
            $campaign->tipoPublico = $request->tipoPublico;
            $campaign->tipoObjetivo = $request->tipoObjetivo;
            $campaign->tipoAudiencia = $request->tipoAudiencia;
            $campaign->interesPublico = $request->interesPublico;
            $campaign->novedad = $request->novedad;
            $campaign->actualidad = $request->actualidad;
            $campaign->autoridadCliente = $request->autoridadCliente;
            $campaign->mediaticoCliente = $request->mediaticoCliente;
            $campaign->autoridadVoceros = $request->autoridadVoceros;
            $campaign->mediaticoVoceros = $request->mediaticoVoceros;
            /*$campaign->pesoPublico = $request->pesoPublico;
            $campaign->pesoObjetivo = $request->pesoObjetivo;
            $campaign->pesoAudiencia = $request->pesoAudiencia;
            $campaign->pesoInteresPublico = $request->pesoInteresPublico;
            $campaign->pesoNovedad = $request->pesoNovedad;
            $campaign->pesoActualidad = $request->pesoActualidad;
            $campaign->pesoAutoridadCliente = $request->pesoAutoridadCliente;
            $campaign->pesoMediaticoCliente = $request->pesoMediaticoCliente;
            $campaign->pesoAutoridadVoceros = $request->pesoAutoridadVoceros;
            $campaign->pesoMediaticoVoceros = $request->pesoMediaticoVoceros;*/
            if (!$campaign->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha actualizado',
                ], 500);
            }

            // Mover directorio de la campaña
            /*$newCliente = Cliente::find($campaign->cliente_id);

            if($oldCliente->id != $newCliente->id){
                Storage::move('clientes/'.$oldCliente->alias.'/'.$campaign->alias, 'clientes/'.$newCliente->alias.'/'.$campaign->alias);
            }*/

            // Datos Opcionales
            $campaign->idCampaignGroup = isset($request->idCampaignGroup) ? $request->idCampaignGroup : null;
            $campaign->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$campaign->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha actualizado',
                ], 500);
            }

            if (isset($request->plataformas)) {
          
                foreach ($request->plataformas as $plataforma) {
                    # code...
                    $campaignPlataforma = CampaignPlataforma::updateOrCreate(
                        ['campaign_id' => $campaign->id, 'plataforma_id' => $plataforma['plataforma_id']],
                        ['meta' => $plataforma['meta']]
                    );
                }
      
            }

            if (isset($request->etiquetas)) {
          
                $idsEtiqueta = array();
                foreach ($request->etiquetas as $slug) {
                    # code...
                    $etiqueta = Etiqueta::firstOrCreate(['slug' => $slug]);
                    array_push($idsEtiqueta, $etiqueta->id);
                }
        
                $campaign->etiquetas()->sync($idsEtiqueta);
      
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La campaña se ha actualizado correctamente',
                'campaign' => $campaign,
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
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function destroy(Campaign $campaign)
    {
        //
        try {
            DB::beginTransaction();

            $existsPlanMedio = PlanMedio::where('campaign_id', $campaign->id)->exists();

            if($existsPlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La campaña se encuentra relacionada con diferentes planes de medios.',
                ], 400);
            }

            CampaignPlataforma::where('campaign_id', $campaign->id)->delete();
            CampaignVocero::where('campaign_id', $campaign->id)->delete();

            if (!$campaign->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La campaña se ha eliminado correctamente',
                'campaign' => $campaign,
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
        $campaigns = Campaign::with('cliente','campaignGroup')->where('cliente_id', $idCliente)->get();

        return response()->json([
            'ready' => true,
            'campaigns' => $campaigns->values(),
        ]);
    }

    public function getListByLogged()
    {
        /*$campaigns = Campaign::with('cliente','campaignGroup','agente','campaignResponsables.user')->whereHas('campaignResponsables', function (Builder $query) {
            $query->where('user_id', auth()->user()->id);
        })->get();*/

        $idsCampaign = auth()->user()->myCampaigns();

        if(auth()->user()->hasAnyRole(['admin','super-admin'])){

            $campaigns = Campaign::with(
                'cliente',
                'campaignGroup',
                'agente',
                'campaignResponsables.user',
            )
            ->get();

        }else{

            $campaigns = Campaign::with(
                'cliente',
                'campaignGroup',
                'agente',
                'campaignResponsables.user',
            )
            ->whereIn('campaigns.id', $idsCampaign)
            ->get();

        }

        return response()->json([
            'ready' => true,
            'campaigns' => $campaigns->values(),
        ]);
    }

    public function getListForReporte(Request $request)
    {
        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatoria.',
                'campaigns.present' => 'Campañas es obligatoria.',
                'voceros.present' => 'Voceros es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'voceros' => ['present','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            set_time_limit(300);

            $params = $request->all();

            $idCliente = $params["idCliente"];
            $idsCampaign = $params["campaigns"];
            $idsVocero = $params["voceros"];

            if(!empty($idsCampaign) && empty($idsVocero)){

                $campaigns = Campaign::with(
                    'agente',
                    'campaignVoceros.vocero',
                    'planMedios.detallePlanMedios.bitacoras',
                    'planMedios.detallePlanMedios.resultados.medioPlataforma.plataformaClasificacion',
                )->where('cliente_id', $idCliente)->whereIn('id', $idsCampaign)->get();

            }elseif(!empty($idsVocero) && empty($idsCampaign)){

                $campaigns = Campaign::with(
                    'agente',
                    'campaignVoceros.vocero',
                    'planMedios.detallePlanMedios.bitacoras',
                    'planMedios.detallePlanMedios.resultados.medioPlataforma.plataformaClasificacion',
                )->where('cliente_id', $idCliente)->whereHas('campaignVoceros', function (Builder $query) use ($idsVocero){
                    $query->whereIn('idVocero', $idsVocero);
                })->get();

            }else{

                $campaigns = Campaign::with(
                    'agente',
                    'campaignVoceros.vocero',
                    'planMedios.detallePlanMedios.bitacoras',
                    'planMedios.detallePlanMedios.resultados.medioPlataforma.plataformaClasificacion',
                )->where('cliente_id', $idCliente)->whereIn('id', $idsCampaign)->whereHas('campaignVoceros', function (Builder $query) use ($idsVocero){
                    $query->whereIn('idVocero', $idsVocero);
                })->get();

            }

            $campaigns->map(function($campaign){
                $campaign->planMedios->map(function($planMedio){
                    $planMedio->detallePlanMedios->map(function($detallePlanMedio){
                        if($detallePlanMedio->vinculado){
                            $detallePlanMedio->detallePlanMedioPadre->bitacoras;
                        }
                        return $detallePlanMedio;
                    });
                    return $planMedio;
                });
                return $campaign;
            });
    
            return response()->json([
                'ready' => true,
                'campaigns' => $campaigns,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function valorar(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'campaign_id.required' => 'La campaña es obligatorio.',
                'campaign_id.exists' => 'Seleccione una Campaña valida.',
                'tipoPublico.required' => 'El Publico es obligatorio.',
                'tipoPublico.integer' => 'Seleccione un Publico valido.',
                'tipoObjetivo.required' => 'El Objetivo es obligatorio.',
                'tipoObjetivo.integer' => 'Seleccione un Objetivo valido.',
                'tipoAudiencia.required' => 'La Audiencia es obligatorio.',
                'tipoAudiencia.integer' => 'Seleccione una Audiencia valido.',
            ];

            $validator = Validator::make($request->all(), [
                'campaign_id' => ['required','exists:campaigns,id'],
                'tipoPublico' => ['required','integer'],
                'tipoObjetivo' => ['required','integer'],
                'tipoAudiencia' => ['required','integer'],
                'interesPublico' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'novedad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'actualidad' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoCliente' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'autoridadVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                'mediaticoVoceros' => [
                    'required',
                    Rule::in([0, 1, 2, 3, 4, 5]),
                ],
                /*'pesoPublico' => ['required','integer'],
                'pesoObjetivo' => ['required','integer'],
                'pesoAudiencia' => ['required','integer'],
                'pesoInteresPublico' => ['required','integer'],
                'pesoNovedad' => ['required','integer'],
                'pesoActualidad' => ['required','integer'],
                'pesoAutoridadCliente' => ['required','integer'],
                'pesoMediaticoCliente' => ['required','integer'],
                'pesoAutoridadVoceros' => ['required','integer'],
                'pesoMediaticoVoceros' => ['required','integer'],*/
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $campaign = Campaign::find($request->campaign_id);

            // Datos Obligatorios
            $campaign->tipoPublico = $request->tipoPublico;
            $campaign->tipoObjetivo = $request->tipoObjetivo;
            $campaign->tipoAudiencia = $request->tipoAudiencia;
            $campaign->interesPublico = $request->interesPublico;
            $campaign->novedad = $request->novedad;
            $campaign->actualidad = $request->actualidad;
            $campaign->autoridadCliente = $request->autoridadCliente;
            $campaign->mediaticoCliente = $request->mediaticoCliente;
            $campaign->autoridadVoceros = $request->autoridadVoceros;
            $campaign->mediaticoVoceros = $request->mediaticoVoceros;
            /*$campaign->pesoPublico = $request->pesoPublico;
            $campaign->pesoObjetivo = $request->pesoObjetivo;
            $campaign->pesoAudiencia = $request->pesoAudiencia;
            $campaign->pesoInteresPublico = $request->pesoInteresPublico;
            $campaign->pesoNovedad = $request->pesoNovedad;
            $campaign->pesoActualidad = $request->pesoActualidad;
            $campaign->pesoAutoridadCliente = $request->pesoAutoridadCliente;
            $campaign->pesoMediaticoCliente = $request->pesoMediaticoCliente;
            $campaign->pesoAutoridadVoceros = $request->pesoAutoridadVoceros;
            $campaign->pesoMediaticoVoceros = $request->pesoMediaticoVoceros;*/
            if (!$campaign->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no se ha valorado',
                ], 500);
            }

            if (isset($request->etiquetas)) {
          
                $idsEtiqueta = array();
                foreach ($request->etiquetas as $slug) {
                    # code...
                    $etiqueta = Etiqueta::firstOrCreate(['slug' => $slug]);
                    array_push($idsEtiqueta, $etiqueta->id);
                }
        
                $campaign->etiquetas()->sync($idsEtiqueta);
      
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La campaña se ha valorado correctamente',
                'campaign' => $campaign,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByDates(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin;
            
            $idsCampaign = auth()->user()->myCampaigns();

            if(auth()->user()->hasAnyRole(['admin','super-admin'])){

                $campaigns = Campaign::with(
                    'cliente',
                    'planMedios',
                )->whereDate('campaigns.fechaFin', '>=', $fechaInicio)
                ->whereDate('campaigns.fechaFin', '<=', $fechaFin)
                ->get();

            }else{

                $campaigns = Campaign::with(
                    'cliente',
                    'planMedios',
                )->whereDate('campaigns.fechaFin', '>=', $fechaInicio)
                ->whereDate('campaigns.fechaFin', '<=', $fechaFin)
                ->whereIn('campaigns.id', $idsCampaign)
                ->get();

            }

            return response()->json([
                'ready' => true,
                'campaigns' => $campaigns->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
