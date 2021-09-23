<?php

namespace App\Http\Controllers;

use App\DetallePlanResultadoPlataforma;
use Illuminate\Http\Request;

use App\Campaign;
use App\Persona;
use App\Plataforma;
use App\ResultadoPlataforma;
use App\DetallePlanMedio;

use Validator;
use DB;
use Crypt;

use Illuminate\Database\Eloquent\Builder;

class DetallePlanResultadoPlataformaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $detallePlanResultadoPlataformas = DetallePlanResultadoPlataforma::all();

        return response()->json([
            'ready' => true,
            'detallePlanResultadoPlataformas' => $detallePlanResultadoPlataformas,
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
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DetallePlanResultadoPlataforma  $detallePlanResultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function show(DetallePlanResultadoPlataforma $detallePlanResultadoPlataforma)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DetallePlanResultadoPlataforma  $detallePlanResultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function edit(DetallePlanResultadoPlataforma $detallePlanResultadoPlataforma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DetallePlanResultadoPlataforma  $detallePlanResultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DetallePlanResultadoPlataforma $detallePlanResultadoPlataforma)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\DetallePlanResultadoPlataforma  $detallePlanResultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function destroy(DetallePlanResultadoPlataforma $detallePlanResultadoPlataforma)
    {
        //
    }

    private function getIdDetallePlanMedioPadre($idDetallePlanMedio)
    {

        $detallePlanMedio = DetallePlanMedio::find($idDetallePlanMedio);
    
        return  $detallePlanMedio->vinculado ? $detallePlanMedio->idDetallePlanMedioPadre : $detallePlanMedio->id;
    }

    private function getPathDir($idDetallePlanMedio)
    {
        $idDPM = $this->getIdDetallePlanMedioPadre($idDetallePlanMedio);
        $detallePlanMedio = DetallePlanMedio::find($idDPM);
        $clienteAlias = $detallePlanMedio->planMedio->campaign->cliente->alias;
        $campaignAlias = $detallePlanMedio->planMedio->campaign->alias;
        $idPM = $detallePlanMedio->planMedio->id;
        return "${clienteAlias}/${campaignAlias}/pm{$idPM}";
    }

    public function getByIdEncriptado($idEncriptado)
    {

        try {
            $idDesencriptado = Crypt::decrypt($idEncriptado);
        
            $DPRP = DetallePlanResultadoPlataforma::find($idDesencriptado);
        
            if(is_null($DPRP)){
        
                return response()->json([
                    'ready' => false,
                    'message' => 'Resultado no encontrado',
                ], 404);
            
            }else{
        
                $RP = ResultadoPlataforma::with(
                    'programaContacto.contacto',
                    'programaContacto.programa.medio',
                    'medioPlataforma.plataformaClasificacion.plataforma',
                )->find($DPRP->idResultadoPlataforma);
        
                $DPM = DetallePlanMedio::with(
                    'planMedio.campaign',
                    'voceros',
                )->find($DPRP->idDetallePlanMedio);

                $DPM->ruta_foto = $this->getPathDir($DPM->id);
        
                if(empty($RP->foto)){
                    $RP->imagen = base64_encode(file_get_contents(storage_path('app/clientes/') . 'reporte_img_default.jpg'));
                }elseif(!file_exists(storage_path('app/clientes/') . $DPM->ruta_foto . '/' . $RP->foto)){
                    $RP->imagen = base64_encode(file_get_contents(storage_path('app/clientes/') . 'reporte_img_default.jpg'));
                }else{
                    $RP->imagen = base64_encode(file_get_contents(storage_path('app/clientes/') . $DPM->ruta_foto . '/' . $RP->foto));
                }    

                $DPRP->RP = $RP;
                $DPRP->DPM = $DPM;

                return response()->json([
                    'ready' => true,
                    'DPRP' => $DPRP,
                ]);

            }

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
                'message' => 'ID no puede ser desencriptado',
            ], 500);
        }
       
    }

    public function getCountByPlataformas()
    {

        $data = DetallePlanResultadoPlataforma::selectRaw("plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, COUNT(1) as cantidad")
        ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
        ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
        ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
        ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
        ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
        ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
        ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
        ->whereNull('resultado_plataformas.deleted_at')
        ->whereNull('detalle_plan_medios.deleted_at')
        ->groupBy("idPlataforma", "Plataforma")
        ->orderByRaw("idPlataforma ASC")
        ->get();
    
        return $data;
    }

    public function getValorizadoByYears()
    {

        $data = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, YEAR(resultado_plataformas.fechaPublicacion) as year, programa_plataformas.valor,
        plataformas.id as idPlataforma, resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, tipo_cambios.TC")
        ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
        ->leftJoin("tipo_cambios", "tipo_cambios.id", "=", "resultado_plataformas.idTipoCambio")
        ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
        ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
        ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
        ->join("programa_contactos", "programa_contactos.id", "=", "resultado_plataformas.idProgramaContacto")
        ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
        ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
        ->join("medios", "medios.id", "=", "programas.medio_id")
        ->leftJoin('programa_plataformas', function ($join) { // VALOR DE LA PLATAFORMA EN EL PROGRAMA
            $join->on('medio_plataformas.id', '=', 'programa_plataformas.idMedioPlataforma')
                ->on('programas.id', '=', 'programa_plataformas.programa_id');
        })
        ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
        ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
        ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
        ->whereNull('resultado_plataformas.deleted_at')
        ->whereNull('detalle_plan_medios.deleted_at')
        ->get()->map(function($DPRP) {
      
            switch ($DPRP->idPlataforma) {
              case 1:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->TC));
                break;
      
              case 2:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->segundos) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->segundos*$DPRP->TC));
                break;
              
              case 3:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->segundos) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->segundos*$DPRP->TC));
                break;
      
              case 5:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->cm2) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->cm2*$DPRP->TC));
                break;
      
              case 9:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->TC));
                break;   
              
              default:
                # code...
                $DPRP->valorizado = ((empty($DPRP->valor) || empty($DPRP->TC)) ? 0 : ($DPRP->valor*$DPRP->TC));
                break;
            }
      
            return $DPRP;
        })->groupBy('year')->map(function($item){
            return $item->sum('valorizado');
        });
    
        return $data;
    }

    public function impactosPorCampanasAndPlataformas(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("campaigns.id as idCampaign, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("idCampaign", "idPlataforma")
                ->orderByRaw("idCampaign ASC, idPlataforma ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("campaigns.id as idCampaign, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("idCampaign", "idPlataforma")
                ->orderByRaw("idCampaign ASC, idPlataforma ASC")
                ->get();
        
            }
        
            $idsCampaign = $data->map(function($item){
                return $item->idCampaign;
            })->unique()->values();
        
            $idsPlataforma = $data->map(function($item){
                return $item->idPlataforma;
            })->unique()->values();
        
            $campaigns = Campaign::whereIn("id", $idsCampaign)->orderBy('id', 'desc')->get();
            $plataformas = Plataforma::whereIn("id", $idsPlataforma)->orderBy('id')->get();
        
            return response()->json([
                'ready' => true,
                'data' => $data,
                'campaigns' => $campaigns,
                'plataformas' => $plataformas,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorCampanasAndPlataformasByLogged(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 

            $idsCampaign = Campaign::whereHas('campaignResponsables', function (Builder $query) {
                $query->where('user_id', auth()->user()->id);
            })->get()->pluck('id');
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("campaigns.id as idCampaign, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                //->where('plan_medios.user_id', auth()->user()->id)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("idCampaign", "idPlataforma")
                ->orderByRaw("idCampaign ASC, idPlataforma ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("campaigns.id as idCampaign, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->whereIn('campaigns.id', $idsCampaign)
                //->where('plan_medios.user_id', auth()->user()->id)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("idCampaign", "idPlataforma")
                ->orderByRaw("idCampaign ASC, idPlataforma ASC")
                ->get();
        
            }
        
            $idsCampaign = $data->map(function($item){
                return $item->idCampaign;
            })->unique()->values();
        
            $idsPlataforma = $data->map(function($item){
                return $item->idPlataforma;
            })->unique()->values();
        
            $campaigns = Campaign::whereIn("id", $idsCampaign)->orderBy('id', 'desc')->get();
            $plataformas = Plataforma::whereIn("id", $idsPlataforma)->orderBy('id')->get();
        
            return response()->json([
                'ready' => true,
                'data' => $data,
                'campaigns' => $campaigns,
                'plataformas' => $plataformas,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }
    
    public function impactosPorVocerosAndPlataformas(Request $request){
    
        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medio_vocero.idVocero, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas", "personas.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->groupBy("idVocero", "idPlataforma")
                ->orderByRaw("idVocero ASC, idPlataforma ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medio_vocero.idVocero, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas", "personas.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->groupBy("idVocero", "idPlataforma")
                ->orderByRaw("idVocero ASC, idPlataforma ASC")
                ->get();
        
            }
        
            $idsVocero = $data->map(function($item){
                return $item->idVocero;
            })->unique()->values();
        
            $idsPlataforma = $data->map(function($item){
                return $item->idPlataforma;
            })->unique()->values();
        
            $voceros = Persona::whereIn("id", $idsVocero)->orderBy('id', 'desc')->get();
            $plataformas = Plataforma::whereIn("id", $idsPlataforma)->orderBy('id')->get();
        
            return response()->json([
                'ready' => true,
                'data' => $data,
                'voceros' => $voceros,
                'plataformas' => $plataformas,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorVocerosAndPlataformasByLogged(Request $request){
    
        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 

            $idsCampaign = Campaign::whereHas('campaignResponsables', function (Builder $query) {
                $query->where('user_id', auth()->user()->id);
            })->get()->pluck('id');
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medio_vocero.idVocero, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas", "personas.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                //->where('plan_medios.user_id', auth()->user()->id)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->groupBy("idVocero", "idPlataforma")
                ->orderByRaw("idVocero ASC, idPlataforma ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medio_vocero.idVocero, plataformas.id as idPlataforma, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas", "personas.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereIn('campaigns.id', $idsCampaign)
                //->where('plan_medios.user_id', auth()->user()->id)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->groupBy("idVocero", "idPlataforma")
                ->orderByRaw("idVocero ASC, idPlataforma ASC")
                ->get();
        
            }
        
            $idsVocero = $data->map(function($item){
                return $item->idVocero;
            })->unique()->values();
        
            $idsPlataforma = $data->map(function($item){
                return $item->idPlataforma;
            })->unique()->values();
        
            $voceros = Persona::whereIn("id", $idsVocero)->orderBy('id', 'desc')->get();
            $plataformas = Plataforma::whereIn("id", $idsPlataforma)->orderBy('id')->get();
        
            return response()->json([
                'ready' => true,
                'data' => $data,
                'voceros' => $voceros,
                'plataformas' => $plataformas,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorTipoTier(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medios.tipoTier as tipoTier, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoTier")
                ->orderByRaw("tipoTier ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medios.tipoTier as tipoTier, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoTier")
                ->orderByRaw("tipoTier ASC")
                ->get();
        
            }
        
            return response()->json([
                'ready' => true,
                'data' => $data,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorTipoTierByLogged(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin;
            
            $idsCampaign = Campaign::whereHas('campaignResponsables', function (Builder $query) {
                $query->where('user_id', auth()->user()->id);
            })->get()->pluck('id');
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medios.tipoTier as tipoTier, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoTier")
                ->orderByRaw("tipoTier ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("detalle_plan_medios.tipoTier as tipoTier, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->whereIn('campaigns.id', $idsCampaign)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoTier")
                ->orderByRaw("tipoTier ASC")
                ->get();
        
            }
        
            return response()->json([
                'ready' => true,
                'data' => $data,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorTipoRegion(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("medios.tipoRegion as tipoRegion, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
                ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
                ->join("medios", "medios.id", "=", "programas.medio_id")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoRegion")
                ->orderByRaw("tipoRegion ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("medios.tipoRegion as tipoRegion, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
                ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
                ->join("medios", "medios.id", "=", "programas.medio_id")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoRegion")
                ->orderByRaw("tipoRegion ASC")
                ->get();
        
            }
        
            return response()->json([
                'ready' => true,
                'data' => $data,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function impactosPorTipoRegionByLogged(Request $request){

        try {
    
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'idCliente' => ['nullable','exists:clientes,id'],
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
        
            $idCliente = isset($request->idCliente) ? $request->idCliente : null;
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin;
            
            $idsCampaign = Campaign::whereHas('campaignResponsables', function (Builder $query) {
                $query->where('user_id', auth()->user()->id);
            })->get()->pluck('id');
        
            if(isset($idCliente)){
        
                $data = DetallePlanResultadoPlataforma::selectRaw("medios.tipoRegion as tipoRegion, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
                ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
                ->join("medios", "medios.id", "=", "programas.medio_id")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoRegion")
                ->orderByRaw("tipoRegion ASC")
                ->get();
        
            }else{
        
                $data = DetallePlanResultadoPlataforma::selectRaw("medios.tipoRegion as tipoRegion, COUNT(1) as cantidad")
                ->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
                ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
                ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
                ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
                ->join("medios", "medios.id", "=", "programas.medio_id")
                ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
                ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
                ->whereIn('campaigns.id', $idsCampaign)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->groupBy("tipoRegion")
                ->orderByRaw("tipoRegion ASC")
                ->get();
        
            }
        
            return response()->json([
                'ready' => true,
                'data' => $data,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }
}
