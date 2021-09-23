<?php

namespace App\Http\Controllers;

use App\ResultadoPlataforma;
use Illuminate\Http\Request;

use App\Atributo;
use App\Bitacora;
use App\PlanMedio;
use App\Plataforma;
use App\Persona;
use App\Campaign;
use App\Cliente;
use App\DetallePlanMedio;
use App\DetallePlanResultadoPlataforma;
use App\Link;
use App\TipoCambio;
use App\User;
use App\Reporte;
use Illuminate\Validation\Rule;
use Validator;
use DB;
use Storage;
use Crypt;
use Illuminate\Support\Str;

use Mail;
use App\Mail\ReporteDestinatario as ReporteDestinatarioMail;
use App\Mail\ImpactosDestinatario as ImpactosDestinatarioMail;

class ResultadoPlataformaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $resultadoPlataformas = ResultadoPlataforma::all();

        return response()->json([
            'ready' => true,
            'resultadoPlataformas' => $resultadoPlataformas,
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
     * @param  \App\ResultadoPlataforma  $resultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function show(ResultadoPlataforma $resultadoPlataforma)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ResultadoPlataforma  $resultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function edit(ResultadoPlataforma $resultadoPlataforma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ResultadoPlataforma  $resultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ResultadoPlataforma $resultadoPlataforma)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ResultadoPlataforma  $resultadoPlataforma
     * @return \Illuminate\Http\Response
     */
    public function destroy(ResultadoPlataforma $resultadoPlataforma)
    {
        //
    }

    private function deleteRPAndDPRP($idDetallePlanMedio)
    {
        $idsDPM = DetallePlanMedio::where('id', $idDetallePlanMedio)
        ->orWhere('idDetallePlanMedioPadre', $idDetallePlanMedio)
        ->get()->map(function($detallePlanMedio){
                return $detallePlanMedio->id;
        });

        $idsRP=DetallePlanResultadoPlataforma::select('idResultadoPlataforma')
        ->whereIn('idDetallePlanMedio',$idsDPM)->distinct()
        ->get()->map(function($detallePlanResultadoPlataforma){
            return $detallePlanResultadoPlataforma->idResultadoPlataforma;
        });
    
        ResultadoPlataforma::whereIn('id',$idsRP)->delete();
        DetallePlanResultadoPlataforma::whereIn('idResultadoPlataforma',$idsRP)->delete();
    }

    public function saveResultados(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idDetallePlanMedio.required' => 'El Detalle de Plan de Medio es obligatoria.',
                'idDetallePlanMedio.exists' => 'Seleccione una Detalle de Plan de Medio valida.',
                'observacion.required' => 'Observacion es obligatorio.',
                'idTipoComunicacion.required' => 'El Tipo de Comunicacion es obligatorio.',
                'idTipoComunicacion.exists' => 'Seleccione un Tipo de Comunicacion valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idDetallePlanMedio' => ['required','exists:detalle_plan_medios,id'],
                'observacion' => ['required'],
                'idTipoComunicacion' => [
                    'required',
                    Rule::exists('tipo_atributos','id')->where(function ($query) {
                        $atributo = Atributo::where('slug', 'comunicacion')->first();
                        $query->where('atributo_id', $atributo->id);
                    }),
                ],
                'status' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Creamos o actualizamos la Bitacora de Resultado
            $bitacora = Bitacora::updateOrCreate(
                ['idDetallePlanMedio' => $request->idDetallePlanMedio, 'estado' => 5],
                ['tipoBitacora' => 1, 'observacion' => $request->observacion, 'idTipoComunicacion' => $request->idTipoComunicacion, 'user_id' => auth()->user()->id]
            );

            if (!$bitacora->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Los resultados no se han creado',
                ], 500);
            }

            // Cambiamos los estados de los DPMs
            $status = json_decode($request->status);

            $messagesStatus = [
                'status.*.idDetallePlanMedio.required' => 'idDetallePlanMedio es obligatorio para cada registro.',
                'status.*.idDetallePlanMedio.exists' => 'Selecione idDetallePlanMedio validos para cada registro.',
                'status.*.statusPublicado.required' => 'Estado es obligatorio para cada registro.',
                'status.*.statusPublicado.in' => 'Selecione Estado validos para cada registro.',
            ];

            $validatorStatus = Validator::make($request->only('status'), [
                'status.*.idDetallePlanMedio' => ['required','exists:detalle_plan_medios,id'],
                'status.*.statusPublicado' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
            ], $messagesStatus);

            if ($validatorStatus->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validatorStatus->errors(),
                ], 400);
            }

            foreach ($status as $statu) {

                $dpmStatus = DetallePlanMedio::find($statu->idDetallePlanMedio);
                $dpmStatus->statusPublicado = $statu->statusPublicado;
                if (!$dpmStatus->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Error al intentar actualizar un estado',
                    ], 500);
                }
            }

            // Creamos los Resultados
            if (isset($request->resultados)) {

                // Eliminamos los registros actuales
                $this->deleteRPAndDPRP($request->idDetallePlanMedio);

                $resultados = json_decode($request->resultados);

                $messagesResultados = [
                    'resultados.*.idProgramaContacto.required' => 'Contacto/Programa es obligatorio para cada registro.',
                    'resultados.*.idProgramaContacto.exists' => 'Selecione Contacto/Programa validos para cada registro.',
                    'resultados.*.idMedioPlataforma.required' => 'Plataforma es obligatorio para cada registro.',
                    'resultados.*.fechaPublicacion.required' => 'La Fecha de Publicacion es obligatorio para cada registro.',
                    'resultados.*.fechaPublicacion.date' => 'Seleccione una Fecha de Publicacion validas para cada registro.',
                    'resultados.*.foto.mimes' => 'Solo se permiten archivos de tipo .doc y .docx.',
                ];
    
                $validatorResultados = Validator::make($request->only('resultados'), [
                    //'resultados' => ['required','array'],
                    'resultados.*.idProgramaContacto' => ['required','exists:programa_contactos,id'],
                    'resultados.*.idMedioPlataforma' => ['required','exists:medio_plataformas,id'],
                    'resultados.*.fechaPublicacion' => ['required','date'],
                    //'resultados.*.foto' => ['nullable','mimes:png,jpg,jpeg'],
                    'resultados.*.idsDetallePlanMedio' => ['required','array'],
                ], $messagesResultados);
    
                if ($validatorResultados->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorResultados->errors(),
                    ], 400);
                }

                foreach ($resultados as $resultado) {

                    // Datos Obligatorios
                    $data = array(
                        'idProgramaContacto' => $resultado->idProgramaContacto,
                        'idMedioPlataforma' => $resultado->idMedioPlataforma,
                        'fechaPublicacion' => $resultado->fechaPublicacion,
                    );

                    $resultadoPlataforma = ResultadoPlataforma::create($data);
                    if (!$resultadoPlataforma->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los resultados no se han creado',
                        ], 500);
                    }

                    // Datos Opcionales
                    $resultadoPlataforma->segundos = isset($resultado->segundos) ? $resultado->segundos : null;
                    $resultadoPlataforma->ancho = isset($resultado->ancho) ? $resultado->ancho : null;
                    $resultadoPlataforma->alto = isset($resultado->alto) ? $resultado->alto : null;
                    $resultadoPlataforma->cm2 = isset($resultado->cm2) ? $resultado->cm2 : null;
                    $resultadoPlataforma->foto = isset($resultado->foto) ? $resultado->foto : null;
                    if (!$resultadoPlataforma->save()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los resultados no se han creado',
                        ], 500);
                    }

                    $newTC = TipoCambio::orderBy('id', 'desc')->first();
                    $resultadoPlataforma->idTipoCambio = isset($resultado->idTipoCambio) ? $resultado->idTipoCambio : (is_null($newTC) ? null : $newTC->id);
                    if (!$resultadoPlataforma->save()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los resultados no se han creado',
                        ], 500);
                    }

                    if (isset($resultado->url)) {

                        $link = Link::firstOrCreate(
                            ['url' => $resultado->url],
                            ['shortcode' => Str::random(6)]
                        );

                        $resultadoPlataforma->url = url("/r/{$link->shortcode}");
                        if (!$resultadoPlataforma->save()) {
                            DB::rollBack();
                            return response()->json([
                                'ready' => false,
                                'message' => 'Los resultados no se han creado',
                            ], 500);
                        }

                    }

                    if (isset($resultado->fileName) && $request->hasFile($resultado->fileName)) {

                        $messagesPhoto = [
                            $resultado->fileName.'mimes' => 'Solo se permiten archivos de tipo .png,.jpg y .jpeg.',
                        ];
        
                        $validatorPhoto = Validator::make($request->only($resultado->fileName), [
                            $resultado->fileName => ['mimes:png,jpg,jpeg'],
                        ], $messagesPhoto);
        
                        if ($validatorPhoto->fails()) {
                            DB::rollBack();
                            return response()->json([
                                'ready' => false,
                                'message' => 'Los datos enviados no son correctos',
                                'errors' => $validatorPhoto->errors(),
                            ], 400);
                        }
        
                        $photo = $request->file($resultado->fileName);
        
                        $extension = $photo->extension();
                        $namePhoto = Str::random(32). '.' . $extension;
        
                        $detallePlanMedio = DetallePlanMedio::find($request->idDetallePlanMedio);
                        $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
                        $campaign = Campaign::find($planMedio->campaign_id);
                        $cliente = Cliente::find($campaign->cliente_id);

                        // Guardar foto
                        $photo->storeAs(
                            'clientes/'.$cliente->alias.'/'.$campaign->alias.'/pm'.$planMedio->id, $namePhoto
                        );
        
                        $resultadoPlataforma->foto = $namePhoto;
                        $resultadoPlataforma->save();
        
                    }

                    foreach ($resultado->idsDetallePlanMedio as $idDetallePlanMedio) {

                        $DPM = DetallePlanMedio::find($idDetallePlanMedio);
                        if($DPM->statusPublicado == 1){

                            $detallePlanResultadoPlataforma = DetallePlanResultadoPlataforma::create(
                                ['idDetallePlanMedio' => $idDetallePlanMedio, 'idResultadoPlataforma' => $resultadoPlataforma->id]
                            );

                            if (!$detallePlanResultadoPlataforma->id) {
                                DB::rollBack();
                                return response()->json([
                                    'ready' => false,
                                    'message' => 'Los resultados no se han creado',
                                ], 500);
                            }

                        }

                    }

                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los resultados se han creado correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByDPM($idDPM)
    {
        //
        $idsDPM = DetallePlanMedio::where('id', $idDPM)
        ->orWhere('idDetallePlanMedioPadre', $idDPM)
        ->get()->map(function($detallePlanMedio){
                return $detallePlanMedio->id;
        });

        $idsRP=DetallePlanResultadoPlataforma::select('idResultadoPlataforma')
        ->whereIn('idDetallePlanMedio',$idsDPM)->distinct()
        ->get()->map(function($detallePlanResultadoPlataforma){
            return $detallePlanResultadoPlataforma->idResultadoPlataforma;
        });

        $resultados = ResultadoPlataforma::whereIn('id',$idsRP)->get()->map(function($resultado){
            $resultado->idsDetallePlanMedio = $resultado->detallePlanMedios()->get()->map(function($detallePlanMedio){
                return $detallePlanMedio->id;
            });
            return $resultado;
        });

        return response()->json([
            'ready' => true,
            'resultados' => $resultados,
        ]);
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

    public function displayImage($id)
    {

        try {
      
            $RP = ResultadoPlataforma::find($id);

            $DPRP = DetallePlanResultadoPlataforma::where('idResultadoPlataforma', $RP->id)->first();
      
            $DPM = DetallePlanMedio::find($DPRP->idDetallePlanMedio);
            $DPM->ruta_foto = $this->getPathDir($DPM->id);
      
            if(empty($RP->foto)){
                $file = storage_path('app/clientes/') . 'reporte_img_default.jpg';
            }elseif(!file_exists(storage_path('app/clientes/') . $DPM->ruta_foto . '/' . $RP->foto)){
                $file = storage_path('app/clientes/') . 'reporte_img_default.jpg';
            }else{
                $file = storage_path('app/clientes/') . $DPM->ruta_foto . '/' . $RP->foto;
            }
      
        } catch (\Exception $e) {
            $file = storage_path('app/clientes/') . 'reporte_img_default.jpg';
        }
          
        return response()->file($file);
       
    }

    private function getDataForReporte($params)
    {

        $idCliente = $params["idCliente"];
        $idsCampaign = $params["campaigns"];
        $idsVocero = $params["voceros"];
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $idsPlataforma = $params["plataformas"];
        $idsTipoNota = $params["tipoNotas"];
    
        if(!empty($idsCampaign) && empty($idsVocero)){

            if(!empty($idsPlataforma) && empty($idsTipoNota)){

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }elseif(!empty($idsTipoNota) && empty($idsPlataforma)){
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }else{
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }

        }elseif(!empty($idsVocero) && empty($idsCampaign)){

            if(!empty($idsPlataforma) && empty($idsTipoNota)){

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }elseif(!empty($idsTipoNota) && empty($idsPlataforma)){
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }else{

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }

        }else{

            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();

        }
    
        $alcanceTotal = 0;
        $valorizadoTotal = 0;
    
        $data = $DPRPs->map(function($DPRP) use (&$alcanceTotal, &$valorizadoTotal){
    
          $DPRP->idEncriptado = Crypt::encrypt($DPRP->id);
          $DPRP->ruta_foto = $this->getPathDir($DPRP->idDPM);
    
          $alcanceTotal += (empty($DPRP->Alcance) ? 0 : $DPRP->Alcance);
    
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
    
          $valorizadoTotal += $DPRP->valorizado;
    
          return $DPRP;
        });
    
        return array ('data' => $data, 'alcanceTotal' => $alcanceTotal, 'valorizadoTotal' => $valorizadoTotal);
    }

    private function getDataForReporteV2($params)
    {

        $idCliente = $params["idCliente"];
        $idsCampaign = $params["campaigns"];
        $idsVocero = $params["voceros"];
        $idsPlanMedio = $params["planmedios"];
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $idsPlataforma = $params["plataformas"];
        $idsTipoNota = $params["tipoNotas"];
        $idsTipoTier = $params["tipoTiers"];

        $tipoData = $params["tipoData"];
        
        switch ($tipoData) {
        case 1:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;

        case 2:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;

        case 3:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('detalle_plan_medios.tipoTier', $idsTipoTier)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;
        
        case 4:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;
            
        case 5:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;
            
        case 6:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('detalle_plan_medios.tipoTier', $idsTipoTier)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;

        case 7:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('plan_medios.id', $idsPlanMedio)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;

        case 8:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('plan_medios.id', $idsPlanMedio)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;

        case 9:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('plan_medios.id', $idsPlanMedio)
                ->whereIn('detalle_plan_medios.tipoTier', $idsTipoTier)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;
        
        default:
            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('plan_medios.id', $idsPlanMedio)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->whereIn('detalle_plan_medios.tipoTier', $idsTipoTier)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
            break;
        }
    
        $alcanceTotal = 0;
        $valorizadoTotal = 0;
    
        $data = $DPRPs->map(function($DPRP) use (&$alcanceTotal, &$valorizadoTotal){
    
          $DPRP->idEncriptado = Crypt::encrypt($DPRP->id);
          $DPRP->ruta_foto = $this->getPathDir($DPRP->idDPM);
    
          $alcanceTotal += (empty($DPRP->Alcance) ? 0 : $DPRP->Alcance);
    
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
    
          $valorizadoTotal += $DPRP->valorizado;
    
          return $DPRP;
        });
    
        return array ('data' => $data, 'alcanceTotal' => $alcanceTotal, 'valorizadoTotal' => $valorizadoTotal);
    }

    private function getDataForReporteV3($params)
    {

        $idCliente = $params["idCliente"];
        $idsCampaign = $params["campaigns"];
        $idsVocero = $params["voceros"];
        $idsPlanMedio = $params["planmedios"];
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $idsPlataforma = $params["plataformas"];
        $idsTipoNota = $params["tipoNotas"];
        $idsTipoTier = $params["tipoTiers"];

        if(!empty($idsVocero)){
            $query = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin);
        }else{
            $query = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, plan_medios.id as idPlanMedio, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoTier, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin);
        }

        if(!empty($idsCampaign)){
            $query = $query->whereIn('campaigns.id', $idsCampaign);
        }

        if(!empty($idsPlanMedio)){
            $query = $query->whereIn('plan_medios.id', $idsPlanMedio);
        }

        if(!empty($idsPlataforma)){
            $query = $query->whereIn('plataformas.id', $idsPlataforma);
        }

        if(!empty($idsTipoNota)){
            $query = $query->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota);
        }

        if(!empty($idsTipoTier)){
            $query = $query->whereIn('detalle_plan_medios.tipoTier', $idsTipoTier);
        }

        $DPRPs = $query->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
        ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
        ->get();
    
        $alcanceTotal = 0;
        $valorizadoTotal = 0;
    
        $data = $DPRPs->map(function($DPRP) use (&$alcanceTotal, &$valorizadoTotal){
    
          $DPRP->idEncriptado = Crypt::encrypt($DPRP->id);
          $DPRP->ruta_foto = $this->getPathDir($DPRP->idDPM);
        /* Desencriptar nombres -INICIO */
         /* if(isset($DPRP->voceros)){
            $DPRP->voceros = DetallePlanMedio::find($DPRP->idDPM)->voceros()->get()->map(function($item){
                return $item->nombres . " " . $item->apellidos;
            })->implode(" - "); // NOMBRE ENCRIPTADO
        }

        if(isset($DPRP->Vocero)){
            $vocero = Persona::find($DPRP->idVocero);
            $DPRP->Vocero = $vocero->nombres . " " . $vocero->apellidos; // NOMBRE ENCRIPTADO
        } 
    
        $contacto = DetallePlanMedio::find($DPRP->idDPM)->programaContacto->contacto;
        $DPRP->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO */
        
        /* Desencriptar nombres - FINAL */
          $alcanceTotal += (empty($DPRP->Alcance) ? 0 : $DPRP->Alcance);
    
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
    
          $valorizadoTotal += $DPRP->valorizado;
    
          return $DPRP;
        });
    
        return array ('data' => $data, 'alcanceTotal' => $alcanceTotal, 'valorizadoTotal' => $valorizadoTotal);
    }


    /** Utilizar esta API si se encripta la data de Persona */
    private function getDataForReporteENC($params)
    {

        $idCliente = $params["idCliente"];
        $idsCampaign = $params["campaigns"];
        $idsVocero = $params["voceros"];
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $idsPlataforma = $params["plataformas"];
        $idsTipoNota = $params["tipoNotas"];
    
        if(!empty($idsCampaign) && empty($idsVocero)){

            if(!empty($idsPlataforma) && empty($idsTipoNota)){

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin);
        
            }elseif(!empty($idsTipoNota) && empty($idsPlataforma)){
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }else{
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                dpmVoceros.voceros, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin(DB::raw("(select detalle_plan_medio_vocero.idDetallePlanMedio, GROUP_CONCAT(DISTINCT CONCAT(personas.nombres,' ',personas.apellidos) SEPARATOR ' - ') AS voceros
                from detalle_plan_medio_vocero
                join personas on personas.id = detalle_plan_medio_vocero.idVocero
                where detalle_plan_medio_vocero.deleted_at is null
                GROUP by detalle_plan_medio_vocero.idDetallePlanMedio)
                        dpmVoceros"), 
                function($join)
                {
                    $join->on('detalle_plan_medios.id', '=', 'dpmVoceros.idDetallePlanMedio');
                })
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }

        }elseif(!empty($idsVocero) && empty($idsCampaign)){

            if(!empty($idsPlataforma) && empty($idsTipoNota)){

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }elseif(!empty($idsTipoNota) && empty($idsPlataforma)){
        
                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }else{

                $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();
        
            }

        }else{

            $DPRPs = DetallePlanResultadoPlataforma::selectRaw("DISTINCT detalle_plan_resultado_plataformas.*, resultado_plataformas.fechaPublicacion, campaigns.id as idCampaign, campaigns.titulo as Campaign, 
                plan_medios.nombre as PlanMedio, detalle_plan_medios.id as idDPM, detalle_plan_medios.tipoNota, resultado_plataformas.id as idRP, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, 
                programas.nombre as Programa, plataformas.id as idPlataforma, plataformas.descripcion as Plataforma, programa_plataformas.valor, medio_plataformas.valor as MedioPlataforma, plataforma_clasificacions.descripcion as Clasificacion,
                resultado_plataformas.segundos, resultado_plataformas.alto, resultado_plataformas.ancho, resultado_plataformas.cm2, resultado_plataformas.foto, resultado_plataformas.url,
                detalle_plan_medio_vocero.idVocero, CONCAT(personaVocero.nombres,' ',personaVocero.apellidos) as Vocero, medio_plataformas.alcance as Alcance, tipo_cambios.TC")
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
                ->leftJoin("detalle_plan_medio_vocero", "detalle_plan_medio_vocero.idDetallePlanMedio", "=", "detalle_plan_medios.id")
                ->join("personas as personaVocero", "personaVocero.id", "=", "detalle_plan_medio_vocero.idVocero")
                ->whereNull('programa_plataformas.deleted_at')
                ->whereNull('resultado_plataformas.deleted_at')
                ->whereNull('detalle_plan_medios.deleted_at')
                ->whereNull('detalle_plan_medio_vocero.deleted_at')
                ->where('campaigns.cliente_id', $idCliente)
                ->whereIn('detalle_plan_medio_vocero.idVocero', $idsVocero)
                ->whereIn('campaigns.id', $idsCampaign)
                ->whereIn('plataformas.id', $idsPlataforma)
                ->whereIn('detalle_plan_medios.tipoNota', $idsTipoNota)
                ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
                ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
                ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
                ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
                ->get();

        }
    
        $alcanceTotal = 0;
        $valorizadoTotal = 0;
    
        $data = $DPRPs->map(function($DPRP) use (&$alcanceTotal, &$valorizadoTotal){

            if(isset($DPRP->voceros)){
                $DPRP->voceros = DetallePlanMedio::find($DPRP->idDPM)->voceros()->get()->map(function($item){
                    return $item->nombres . " " . $item->apellidos;
                })->implode(" - "); // NOMBRE ENCRIPTADO
            }
    
            if(isset($DPRP->Vocero)){
                $vocero = Persona::find($DPRP->idVocero);
                $DPRP->Vocero = $vocero->nombres . " " . $vocero->apellidos; // NOMBRE ENCRIPTADO
            }
        
            $contacto = DetallePlanMedio::find($DPRP->idDPM)->programaContacto->contacto;
            $DPRP->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO
    
            $DPRP->idEncriptado = Crypt::encrypt($DPRP->id);
            $DPRP->ruta_foto = $this->getPathDir($DPRP->idDPM);

            $alcanceTotal += (empty($DPRP->Alcance) ? 0 : $DPRP->Alcance);

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

            $valorizadoTotal += $DPRP->valorizado;

            return $DPRP;
        });
    
        return array ('data' => $data, 'alcanceTotal' => $alcanceTotal, 'valorizadoTotal' => $valorizadoTotal);
    }

    public function getResultadosForReporte(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatoria.',
                'campaigns.present' => 'Campañas es obligatoria.',
                'voceros.present' => 'Voceros es obligatoria.',
                'plataformas.present' => 'Plataformas es obligatoria.',
                'tipoNotas.present' => 'Tipos de nota es obligatoria.',
                'fechaInicio.required' => 'Desde es obligatoria.',
                'fechaFin.required' => 'Hasta es obligatoria.',
                'isDet.required' => 'Detalles es obligatoria.',
                'isVal.required' => 'Valorizacion es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'voceros' => ['present','array'],
                'plataformas' => ['present','array'],
                'tipoNotas' => ['present','array'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'isDet' => ['required','boolean'],
                'isVal' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $params = $request->all();

            $info = $this->getDataForReporte($params);
            $data = $info['data'];
            $alcanceTotal = $info['alcanceTotal'];
            $valorizadoTotal = $info['valorizadoTotal'];
    
            return response()->json([
                'ready' => true,
                'params' => $params,
                'DPRPs' => $data,
                'alcanceTotal' => $alcanceTotal,
                'valorizadoTotal' => $valorizadoTotal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getResultadosForReporteV2(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatorio.',
                'campaigns.present' => 'Campañas es obligatorio.',
                'voceros.present' => 'Voceros es obligatorio.',
                'planmedios.present' => 'Plan de Medios es obligatorio.',
                'plataformas.present' => 'Plataformas es obligatorio.',
                'tipoNotas.present' => 'Tipos de nota es obligatorio.',
                'tipoTiers.present' => 'Tipos de tier es obligatorio.',
                'fechaInicio.required' => 'Desde es obligatorio.',
                'fechaFin.required' => 'Hasta es obligatorio.',
                'isDet.required' => 'Detalles es obligatorio.',
                'isVal.required' => 'Valorizacion es obligatorio.',
                //'tipoData.required' => 'Tipo de Data es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'voceros' => ['present','array'],
                'planmedios' => ['present','array'],
                'plataformas' => ['present','array'],
                'tipoNotas' => ['present','array'],
                'tipoTiers' => ['present','array'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'isDet' => ['required','boolean'],
                'isVal' => ['required','boolean'],
                /*'tipoData' => [
                    'required',
                    Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                ],*/
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $params = $request->all();

            $info = $this->getDataForReporteV3($params);
            $data = $info['data'];
            $alcanceTotal = $info['alcanceTotal'];
            $valorizadoTotal = $info['valorizadoTotal'];
    
            return response()->json([
                'ready' => true,
                'params' => $params,
                'DPRPs' => $data,
                'alcanceTotal' => $alcanceTotal,
                'valorizadoTotal' => $valorizadoTotal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    /** IMPACTOS POR PLATAFORMAS */

    private function getDataForImpactos($params)
    {

        $idCliente = $params["idCliente"];
        $idsCampaign = $params["campaigns"];
        $idsPlataforma = $params["plataformas"];
        $fechaInicio = isset($params["fechaInicio"]) ? $params["fechaInicio"] : null;
        $fechaFin = isset($params["fechaFin"]) ? $params["fechaFin"] : null;

        if(isset($fechaInicio) && isset($fechaFin)){

            $data = DetallePlanResultadoPlataforma::selectRaw("
            plataformas.id as idPlataforma,
            YEAR(resultado_plataformas.fechaPublicacion) as year,
            MONTH(resultado_plataformas.fechaPublicacion) AS month,
            COUNT(1) as cantidad
            ")->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
            ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
            ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
            ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
            ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->whereNull('resultado_plataformas.deleted_at')
            ->whereNull('detalle_plan_medios.deleted_at')
            ->where('campaigns.cliente_id', $idCliente)
            ->whereIn('campaigns.id', $idsCampaign)
            ->whereIn('plataformas.id', $idsPlataforma)
            ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
            ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
            ->groupBy("idPlataforma", "year", "month")
            ->orderByRaw("year ASC, month ASC, idPlataforma ASC")
            ->get();

        }else{

            $data = DetallePlanResultadoPlataforma::selectRaw("
            plataformas.id as idPlataforma,
            YEAR(resultado_plataformas.fechaPublicacion) as year,
            MONTH(resultado_plataformas.fechaPublicacion) AS month,
            COUNT(1) as cantidad
            ")->join("resultado_plataformas", "detalle_plan_resultado_plataformas.idResultadoPlataforma", "=", "resultado_plataformas.id")
            ->join("medio_plataformas", "medio_plataformas.id", "=", "resultado_plataformas.idMedioPlataforma")
            ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
            ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
            ->join("detalle_plan_medios", "detalle_plan_medios.id", "=", "detalle_plan_resultado_plataformas.idDetallePlanMedio")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->whereNull('resultado_plataformas.deleted_at')
            ->whereNull('detalle_plan_medios.deleted_at')
            ->where('campaigns.cliente_id', $idCliente)
            ->whereIn('campaigns.id', $idsCampaign)
            ->whereIn('plataformas.id', $idsPlataforma)
            ->groupBy("idPlataforma", "year", "month")
            ->orderByRaw("year ASC, month ASC, idPlataforma ASC")
            ->get();

        }
    
        return $data;
    }

    public function getImpactosByPlataformas(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatoria.',
                'campaigns.present' => 'Campañas es obligatoria.',
                'plataformas.present' => 'Plataformas es obligatoria.',
                'fechaInicio.required' => 'Desde es obligatoria.',
                'fechaFin.required' => 'Hasta es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'plataformas' => ['present','array'],
                'fechaInicio' => ['nullable','date'],
                'fechaFin' => ['nullable','date'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $params = $request->all();

            $data = $this->getDataForImpactos($params);
    
            return response()->json([
                'ready' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function generateImpactosByPlataformas(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatoria.',
                'campaigns.present' => 'Campañas es obligatoria.',
                'plataformas.present' => 'Plataformas es obligatoria.',
                'fechaInicio.required' => 'Desde es obligatoria.',
                'fechaFin.required' => 'Hasta es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'plataformas' => ['present','array'],
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

            set_time_limit(300);

            $params = $request->all();

            $filename = $this->createAndSaveImpactosByPlataformas($params);
    
            return response()->json([
                'ready' => true,
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function sendImpactosByPlataformas(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatoria.',
                'campaigns.present' => 'Campañas es obligatoria.',
                'plataformas.present' => 'Plataformas es obligatoria.',
                'fechaInicio.required' => 'Desde es obligatoria.',
                'fechaFin.required' => 'Hasta es obligatoria.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'plataformas' => ['present','array'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'destinatarios' => ['required'],
                'asunto' => ['present'],
                'mensaje' => ['present'],
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

            $usuario = User::find(auth()->user()->id);
    
            $fechaInicio = $params["fechaInicio"];
            $fechaFin = $params["fechaFin"];
        
            $filename = $this->createAndSaveImpactosByPlataformas($params);
        
            $tituloCorreo = "Impactos por Plataformas";
        
            $defaultAsunto = "Impactos por Plataformas";
            $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
        
            $destinatarios = $params["destinatarios"];
        
            $mensaje = $params["mensaje"];
        
            $emailsNotExist = array();
            $failDestinatarios = array();
            $successDestinatarios = array();
        
            // Envio de reporte por correo a los destinatarios
            foreach ($destinatarios as $destinatario) {

                $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                    'email' => ['email'],
                ]);

                if ($validatorEmail->fails()) {
                    array_push($emailsNotExist,$destinatario);
                }else{
                    $tries = 0;
                    $mailSent = false;
            
                    while(!$mailSent && ($tries<3)){
            
                        try {
                
                            Mail::to($destinatario['email'])
                                ->send(new ImpactosDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
                
                            $mailSent = true;
                
                        } catch (\Exception $e) {
                
                        }
                
                        $tries++;
            
                    }
            
                    if($mailSent){
                        array_push($successDestinatarios,$destinatario);
                    }else{
                        array_push($failDestinatarios,$destinatario);
                    }
                }
        
            }

            return response()->json([
                'ready' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }            

    }

    private function createAndSaveImpactosByPlataformas($params)
    {

        $data = $this->getDataForImpactos($params);
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformas = Plataforma::findMany($params["plataformas"]);

        $view =  \View::make('pdf.impactos-plataformas', compact('plataformas', 'cliente', 'fechaInicio', 'fechaFin', 'data'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ImpactosPorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/') . $filename);

        $reporte = Reporte::create([
            'nameReporte' => $filename,
            'createdDate' => date('Y-m-d'),
        ]);
    
        return $filename;
    }

    /** REPORTES */

    public function generateReporte(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatorio.',
                'campaigns.present' => 'Campañas es obligatorio.',
                'voceros.present' => 'Voceros es obligatorio.',
                'planmedios.present' => 'Plan de Medios es obligatorio.',
                'plataformas.present' => 'Plataformas es obligatorio.',
                'tipoNotas.present' => 'Tipos de nota es obligatorio.',
                'tipoTiers.present' => 'Tipos de tier es obligatorio.',
                'fechaInicio.required' => 'Desde es obligatorio.',
                'fechaFin.required' => 'Hasta es obligatorio.',
                'isDet.required' => 'Detalles es obligatorio.',
                'isVal.required' => 'Valorizacion es obligatorio.',
                'tipoReporte.required' => 'Tipo de Reporte es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'voceros' => ['present','array'],
                'planmedios' => ['present','array'],
                'plataformas' => ['present','array'],
                'tipoNotas' => ['present','array'],
                'tipoTiers' => ['present','array'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'isDet' => ['required','boolean'],
                'isVal' => ['required','boolean'],
                'tipoReporte' => [
                    'required',
                    Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18]),
                ],
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
            $tipoReporte = $params["tipoReporte"];
        
            switch ($tipoReporte) {
            case 1:
                $filename = $this->createAndSaveReportePorPlataformas($params);
                break;
        
            case 2:
                $filename = $this->createAndSaveReportePorCampanasAndPlataformas($params);
                break;
            
            case 3:
                $filename = $this->createAndSaveReportePorVocerosAndPlataformas($params);
                break;
            
            case 4:
                $filename = $this->createAndSaveReportePorPlanMediosAndPlataformas($params);
                break;
            
            case 5:
                $filename = $this->createAndSaveReportePorTipoNotasAndPlataformas($params);
                break;

            case 6:
                $filename = $this->createAndSaveReportePorTipoTiersAndPlataformas($params);
                break;
            
            case 7:
                $filename = $this->createAndSaveReportePorTipoNotas($params);
                break;
        
            case 8:
                $filename = $this->createAndSaveReportePorCampanasAndTipoNotas($params);
                break;
                
            case 9:
                $filename = $this->createAndSaveReportePorVocerosAndTipoNotas($params);
                break;

            case 10:
                $filename = $this->createAndSaveReportePorPlanMediosAndTipoNotas($params);
                break;

            case 11:
                $filename = $this->createAndSaveReportePorPlataformasAndTipoNotas($params);
                break;

            case 12:
                $filename = $this->createAndSaveReportePorTipoTiersAndTipoNotas($params);
                break;
            
            case 13:
                $filename = $this->createAndSaveReportePorTipoTiers($params);
                break;
        
            case 14:
                $filename = $this->createAndSaveReportePorCampanasAndTipoTiers($params);
                break;
                
            case 15:
                $filename = $this->createAndSaveReportePorVocerosAndTipoTiers($params);
                break;

            case 16:
                $filename = $this->createAndSaveReportePorPlanMediosAndTipoTiers($params);
                break;

            case 17:
                $filename = $this->createAndSaveReportePorPlataformasAndTipoTiers($params);
                break;

            case 18:
                $filename = $this->createAndSaveReportePorTipoNotasAndTipoTiers($params);
                break;

            default:
                # code...
                break;
            }

            return response()->json([
                'ready' => true,
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }            

    }

    public function sendReporte(Request $request)
    {

        try {

            $messages = [
                'idCliente.required' => 'Cliente es obligatorio.',
                'campaigns.present' => 'Campañas es obligatorio.',
                'voceros.present' => 'Voceros es obligatorio.',
                'planmedios.present' => 'Plan de Medios es obligatorio.',
                'plataformas.present' => 'Plataformas es obligatorio.',
                'tipoNotas.present' => 'Tipos de nota es obligatorio.',
                'tipoTiers.present' => 'Tipos de tier es obligatorio.',
                'fechaInicio.required' => 'Desde es obligatorio.',
                'fechaFin.required' => 'Hasta es obligatorio.',
                'isDet.required' => 'Detalles es obligatorio.',
                'isVal.required' => 'Valorizacion es obligatorio.',
                'tipoReporte.required' => 'Tipo de Reporte es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idCliente' => ['required'],
                'campaigns' => ['present','array'],
                'voceros' => ['present','array'],
                'planmedios' => ['present','array'],
                'plataformas' => ['present','array'],
                'tipoNotas' => ['present','array'],
                'tipoTiers' => ['present','array'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'isDet' => ['required','boolean'],
                'isVal' => ['required','boolean'],
                'tipoReporte' => [
                    'required',
                    Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18]),
                ],
                'destinatarios' => ['required'],
                'asunto' => ['present'],
                'mensaje' => ['present'],
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
            $tipoReporte = $params["tipoReporte"];
        
            switch ($tipoReporte) {
            case 1:
                $this->sendReportePorPlataformas($params);
                break;
        
            case 2:
                $this->sendReportePorCampanasAndPlataformas($params);
                break;
            
            case 3:
                $this->sendReportePorVocerosAndPlataformas($params);
                break;
            
            case 4:
                $this->sendReportePorPlanMediosAndPlataformas($params);
                break;
            
            case 5:
                $this->sendReportePorTipoNotasAndPlataformas($params);
                break;

            case 6:
                $this->sendReportePorTipoTiersAndPlataformas($params);
                break;
            
            case 7:
                $this->sendReportePorTipoNotas($params);
                break;
        
            case 8:
                $this->sendReportePorCampanasAndTipoNotas($params);
                break;
                
            case 9:
                $this->sendReportePorVocerosAndTipoNotas($params);
                break;

            case 10:
                $this->sendReportePorPlanMediosAndTipoNotas($params);
                break;

            case 11:
                $this->sendReportePorPlataformasAndTipoNotas($params);
                break;

            case 12:
                $this->sendReportePorTipoTiersAndTipoNotas($params);
                break;
            
            case 13:
                $this->sendReportePorTipoTiers($params);
                break;
        
            case 14:
                $this->sendReportePorCampanasAndTipoTiers($params);
                break;
                
            case 15:
                $this->sendReportePorVocerosAndTipoTiers($params);
                break;

            case 16:
                $this->sendReportePorPlanMediosAndTipoTiers($params);
                break;

            case 17:
                $this->sendReportePorPlataformasAndTipoTiers($params);
                break;

            case 18:
                $this->sendReportePorTipoNotasAndTipoTiers($params);
                break;
            
            default:
                # code...
                break;
            }

            return response()->json([
                'ready' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }            

    }

    /** REPORTE POR PLATAFORMAS */

    private function countPlat(&$plataformas, $data)
    {
        $count = array();
    
        $count['total'] = 0;
    
        foreach ($plataformas as $key => $plataforma) {
            # code...
    
          $filter = $data->filter(function ($item) use ($plataforma)
          {
            return $plataforma->id == $item->idPlataforma;
          });
    
          $count[$plataforma->id] = count($filter);
    
          $count['total'] += $count[$plataforma->id];
    
          if($count[$plataforma->id] == 0){
            unset($plataformas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformasTotales = Plataforma::findMany($params["plataformas"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlat($plataformasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($plataformasTotales as $plataforma) {
          # code...
          $dataPlatTotal = $data->filter(function ($item) use ($plataforma)
          {
            return $plataforma->id == $item->idPlataforma;
          });
    
          for($i = 0 ; $i < count($dataPlatTotal) ;){
            $dataPlat = $dataPlatTotal->slice($i, 30)->values();
    
            $view =  \View::make('pdf.reporte-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'plataformasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataforma', 'dataPlat', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 30;
          }
    
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlataformas($params);
    
        $tituloCorreo = "Reporte por Plataformas";
    
        $defaultAsunto = "Reporte por Plataformas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {

            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countPlatxTipoNota(&$plataformas, $tipoNotas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($plataformas as $key => $plataforma) {
          # code...
          $count[$plataforma->id]['total'] = 0;
          foreach ($tipoNotas as $tipoNota) {
            # code...
            if(!isset($count['total'][$tipoNota])){
              $count['total'][$tipoNota] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($plataforma, $tipoNota)
            {
              return $plataforma->id == $item->idPlataforma && $tipoNota == $item->tipoNota;
            });
    
            $count[$plataforma->id][$tipoNota] = count($filter);
    
            $count[$plataforma->id]['total'] += $count[$plataforma->id][$tipoNota];
            $count['total'][$tipoNota] += $count[$plataforma->id][$tipoNota];
          }
          $count['total']['total'] += $count[$plataforma->id]['total'];
    
          if($count[$plataforma->id]['total'] == 0){
            unset($plataformas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlataformasAndTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotasTotales = $params["tipoNotas"];
        $plataformasTotales = Plataforma::findMany($params["plataformas"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlatxTipoNota($plataformasTotales, $tipoNotasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($plataformasTotales as $plataforma) {
            # code...
            foreach ($tipoNotasTotales as $tipoNota) {
                # code...
                $dataPlatTipoNotaTotal = $data->filter(function ($item) use ($plataforma, $tipoNota)
                {
                    return $plataforma->id == $item->idPlataforma && $tipoNota == $item->tipoNota;
                });
            
                for($i = 0 ; $i < count($dataPlatTipoNotaTotal) ;){
                    $dataPlatTipoNota = $dataPlatTipoNotaTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-plataformas-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'plataformasTotales', 'tipoNotasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataforma', 'tipoNota', 'dataPlatTipoNota', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-plataformas-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlataformasAndTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlataformasAndTipoNotas($params);
    
        $tituloCorreo = "Reporte por Plataformas";
    
        $defaultAsunto = "Reporte por Plataformas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {

            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countPlatxTipoTier(&$plataformas, $tipoTiers, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($plataformas as $key => $plataforma) {
          # code...
          $count[$plataforma->id]['total'] = 0;
          foreach ($tipoTiers as $tipoTier) {
            # code...
            if(!isset($count['total'][$tipoTier])){
              $count['total'][$tipoTier] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($plataforma, $tipoTier)
            {
              return $plataforma->id == $item->idPlataforma && $tipoTier == $item->tipoTier;
            });
    
            $count[$plataforma->id][$tipoTier] = count($filter);
    
            $count[$plataforma->id]['total'] += $count[$plataforma->id][$tipoTier];
            $count['total'][$tipoTier] += $count[$plataforma->id][$tipoTier];
          }
          $count['total']['total'] += $count[$plataforma->id]['total'];
    
          if($count[$plataforma->id]['total'] == 0){
            unset($plataformas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlataformasAndTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiersTotales = $params["tipoTiers"];
        $plataformasTotales = Plataforma::findMany($params["plataformas"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlatxTipoTier($plataformasTotales, $tipoTiersTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($plataformasTotales as $plataforma) {
            # code...
            foreach ($tipoTiersTotales as $tipoTier) {
                # code...
                $dataPlatTipoTierTotal = $data->filter(function ($item) use ($plataforma, $tipoTier)
                {
                    return $plataforma->id == $item->idPlataforma && $tipoTier == $item->tipoTier;
                });
            
                for($i = 0 ; $i < count($dataPlatTipoTierTotal) ;){
                    $dataPlatTipoTier = $dataPlatTipoTierTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-plataformas-tiers', compact('alcanceTotal', 'valorizadoTotal', 'plataformasTotales', 'tipoTiersTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataforma', 'tipoTier', 'dataPlatTipoTier', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-plataformas-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlataformas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlataformasAndTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlataformasAndTipoTiers($params);
    
        $tituloCorreo = "Reporte por Plataformas";
    
        $defaultAsunto = "Reporte por Plataformas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {

            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    /** REPORTE POR NOTAS */

    private function countTipoNota(&$tipoNotas, $data)
    {
        $count = array();
    
        $count['total'] = 0;
    
        foreach ($tipoNotas as $key => $tipoNota) {
            # code...
    
          $filter = $data->filter(function ($item) use ($tipoNota)
          {
            return $tipoNota == $item->tipoNota;
          });
    
          $count[$tipoNota] = count($filter);
    
          $count['total'] += $count[$tipoNota];
    
          if($count[$tipoNota] == 0){
            unset($tipoNotas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotasTotales = $params["tipoNotas"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoNota($tipoNotasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoNotasTotales as $tipoNota) {
          # code...
          $dataTipoNotaTotal = $data->filter(function ($item) use ($tipoNota)
          {
            return $tipoNota == $item->tipoNota;
          });
    
          for($i = 0 ; $i < count($dataTipoNotaTotal) ;){
            $dataTipoNota = $dataTipoNotaTotal->slice($i, 30)->values();
    
            $view =  \View::make('pdf.reporte-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'tipoNotasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNota', 'dataTipoNota', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 30;
          }
    
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoNotas($params);
    
        $tituloCorreo = "Reporte por Tipos de Nota";
    
        $defaultAsunto = "Reporte por Tipos de Nota";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countTipoNotaxPlat(&$tipoNotas, $plataformas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($tipoNotas as $key => $tipoNota) {
          # code...
          $count[$tipoNota]['total'] = 0;
          foreach ($plataformas as $plataforma) {
            # code...
            if(!isset($count['total'][$plataforma->id])){
              $count['total'][$plataforma->id] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($tipoNota, $plataforma)
            {
              return $tipoNota == $item->tipoNota && $plataforma->id == $item->idPlataforma;
            });
    
            $count[$tipoNota][$plataforma->id] = count($filter);
    
            $count[$tipoNota]['total'] += $count[$tipoNota][$plataforma->id];
            $count['total'][$plataforma->id] += $count[$tipoNota][$plataforma->id];
          }
          $count['total']['total'] += $count[$tipoNota]['total'];
    
          if($count[$tipoNota]['total'] == 0){
            unset($tipoNotas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoNotasAndPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformasTotales = Plataforma::findMany($params["plataformas"]);
        $tipoNotasTotales = $params["tipoNotas"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoNotaxPlat($tipoNotasTotales, $plataformasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoNotasTotales as $tipoNota) {
            # code...
            foreach ($plataformasTotales as $plataforma) {
                # code...
                $dataTipoNotaPlatTotal = $data->filter(function ($item) use ($tipoNota, $plataforma)
                {
                    return $tipoNota == $item->tipoNota && $plataforma->id == $item->idPlataforma;
                });
            
                for($i = 0 ; $i < count($dataTipoNotaPlatTotal) ;){
                    $dataTipoNotaPlat = $dataTipoNotaPlatTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-tiponotas-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'tipoNotasTotales', 'plataformasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNota', 'plataforma', 'dataTipoNotaPlat', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiponotas-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoNotasAndPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoNotasAndPlataformas($params);
    
        $tituloCorreo = "Reporte por Tipos de Nota";
    
        $defaultAsunto = "Reporte por Tipos de Nota";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countTipoNotaxTipoTier(&$tipoNotas, $tipoTiers, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($tipoNotas as $key => $tipoNota) {
          # code...
          $count[$tipoNota]['total'] = 0;
          foreach ($tipoTiers as $tipoTier) {
            # code...
            if(!isset($count['total'][$tipoTier])){
              $count['total'][$tipoTier] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($tipoNota, $tipoTier)
            {
              return $tipoNota == $item->tipoNota && $tipoTier == $item->tipoTier;
            });
    
            $count[$tipoNota][$tipoTier] = count($filter);
    
            $count[$tipoNota]['total'] += $count[$tipoNota][$tipoTier];
            $count['total'][$tipoTier] += $count[$tipoNota][$tipoTier];
          }
          $count['total']['total'] += $count[$tipoNota]['total'];
    
          if($count[$tipoNota]['total'] == 0){
            unset($tipoNotas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoNotasAndTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiersTotales = $params["tipoTiers"];
        $tipoNotasTotales = $params["tipoNotas"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoNotaxTipoTier($tipoNotasTotales, $tipoTiersTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoNotasTotales as $tipoNota) {
            # code...
            foreach ($tipoTiersTotales as $tipoTier) {
                # code...
                $dataTipoNotaTipoTierTotal = $data->filter(function ($item) use ($tipoNota, $tipoTier)
                {
                    return $tipoNota == $item->tipoNota && $tipoTier == $item->tipoTier;
                });
            
                for($i = 0 ; $i < count($dataTipoNotaTipoTierTotal) ;){
                    $dataTipoNotaTipoTier = $dataTipoNotaTipoTierTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-tiponotas-tiers', compact('alcanceTotal', 'valorizadoTotal', 'tipoNotasTotales', 'tipoTiersTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNota', 'tipoTier', 'dataTipoNotaTipoTier', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiponotas-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTipoNotas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoNotasAndTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoNotasAndTipoTiers($params);
    
        $tituloCorreo = "Reporte por Tipos de Nota";
    
        $defaultAsunto = "Reporte por Tipos de Nota";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }


    /** REPORTE POR TIERS */

    private function countTipoTier(&$tipoTiers, $data)
    {
        $count = array();
    
        $count['total'] = 0;
    
        foreach ($tipoTiers as $key => $tipoTier) {
            # code...
    
          $filter = $data->filter(function ($item) use ($tipoTier)
          {
            return $tipoTier == $item->tipoTier;
          });
    
          $count[$tipoTier] = count($filter);
    
          $count['total'] += $count[$tipoTier];
    
          if($count[$tipoTier] == 0){
            unset($tipoTiers[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiersTotales = $params["tipoTiers"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoTier($tipoTiersTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoTiersTotales as $tipoTier) {
          # code...
          $dataTipoTierTotal = $data->filter(function ($item) use ($tipoTier)
          {
            return $tipoTier == $item->tipoTier;
          });
    
          for($i = 0 ; $i < count($dataTipoTierTotal) ;){
            $dataTipoTier = $dataTipoTierTotal->slice($i, 30)->values();
    
            $view =  \View::make('pdf.reporte-tiers', compact('alcanceTotal', 'valorizadoTotal', 'tipoTiersTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTier', 'dataTipoTier', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 30;
          }
    
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoTiers($params);
    
        $tituloCorreo = "Reporte por Tiers";
    
        $defaultAsunto = "Reporte por Tiers";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countTipoTierxPlat(&$tipoTiers, $plataformas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($tipoTiers as $key => $tipoTier) {
          # code...
          $count[$tipoTier]['total'] = 0;
          foreach ($plataformas as $plataforma) {
            # code...
            if(!isset($count['total'][$plataforma->id])){
              $count['total'][$plataforma->id] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($tipoTier, $plataforma)
            {
              return $tipoTier == $item->tipoTier && $plataforma->id == $item->idPlataforma;
            });
    
            $count[$tipoTier][$plataforma->id] = count($filter);
    
            $count[$tipoTier]['total'] += $count[$tipoTier][$plataforma->id];
            $count['total'][$plataforma->id] += $count[$tipoTier][$plataforma->id];
          }
          $count['total']['total'] += $count[$tipoTier]['total'];
    
          if($count[$tipoTier]['total'] == 0){
            unset($tipoTiers[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoTiersAndPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformasTotales = Plataforma::findMany($params["plataformas"]);
        $tipoTiersTotales = $params["tipoTiers"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoTierxPlat($tipoTiersTotales, $plataformasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoTiersTotales as $tipoTier) {
            # code...
            foreach ($plataformasTotales as $plataforma) {
                # code...
                $dataTipoTierPlatTotal = $data->filter(function ($item) use ($tipoTier, $plataforma)
                {
                    return $tipoTier == $item->tipoTier && $plataforma->id == $item->idPlataforma;
                });
            
                for($i = 0 ; $i < count($dataTipoTierPlatTotal) ;){
                    $dataTipoTierPlat = $dataTipoTierPlatTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-tiers-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'tipoTiersTotales', 'plataformasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTier', 'plataforma', 'dataTipoTierPlat', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiers-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoTiersAndPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoTiersAndPlataformas($params);
    
        $tituloCorreo = "Reporte por Tiers";
    
        $defaultAsunto = "Reporte por Tiers";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countTipoTierxTipoNota(&$tipoTiers, $tipoNotas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($tipoTiers as $key => $tipoTier) {
          # code...
          $count[$tipoTier]['total'] = 0;
          foreach ($tipoNotas as $tipoNota) {
            # code...
            if(!isset($count['total'][$tipoNota])){
              $count['total'][$tipoNota] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($tipoTier, $tipoNota)
            {
              return $tipoTier == $item->tipoTier && $tipoNota == $item->tipoNota;
            });
    
            $count[$tipoTier][$tipoNota] = count($filter);
    
            $count[$tipoTier]['total'] += $count[$tipoTier][$tipoNota];
            $count['total'][$tipoNota] += $count[$tipoTier][$tipoNota];
          }
          $count['total']['total'] += $count[$tipoTier]['total'];
    
          if($count[$tipoTier]['total'] == 0){
            unset($tipoTiers[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorTipoTiersAndTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotasTotales = $params["tipoNotas"];
        $tipoTiersTotales = $params["tipoTiers"];
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countTipoTierxTipoNota($tipoTiersTotales, $tipoNotasTotales, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        foreach ($tipoTiersTotales as $tipoTier) {
            # code...
            foreach ($tipoNotasTotales as $tipoNota) {
                # code...
                $dataTipoTierTipoNotaTotal = $data->filter(function ($item) use ($tipoTier, $tipoNota)
                {
                    return $tipoTier == $item->tipoTier && $tipoNota == $item->tipoNota;
                });
            
                for($i = 0 ; $i < count($dataTipoTierTipoNotaTotal) ;){
                    $dataTipoTierTipoNota = $dataTipoTierTipoNotaTotal->slice($i, 30)->values();
            
                    $view =  \View::make('pdf.reporte-tiers-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'tipoTiersTotales', 'tipoNotasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTier', 'tipoNota', 'dataTipoTierTipoNota', 'isVal', 'isDet', 'count'))->render();
                    $pdf = \App::make('dompdf.wrapper');
                    $pdf->loadHTML($view);
                    $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
                    $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
            
                    for ($page = 1; $page <= $pageCount; $page++) {
                        $mpdf->AddPage();
                        $template = $mpdf->importPage($page);
                        $mpdf->useTemplate($template);
                    }
            
                    $printFirstPage = false;
            
                    $i = $i + 30;
                }
            
            }
        }
    
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-tiers-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorTiers_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorTipoTiersAndTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorTipoTiersAndTipoNotas($params);
    
        $tituloCorreo = "Reporte por Tiers";
    
        $defaultAsunto = "Reporte por Tiers";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    /** REPORTE POR CAMPAÑAS */

    private function countCampxPlat(&$campanas, $plataformas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($campanas as $key => $campana) {
          # code...
          $count[$campana->id]['total'] = 0;
          foreach ($plataformas as $plataforma) {
            # code...
            if(!isset($count['total'][$plataforma->id])){
              $count['total'][$plataforma->id] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($campana, $plataforma)
            {
              return $campana->id == $item->idCampaign && $plataforma->id == $item->idPlataforma;
            });
    
            $count[$campana->id][$plataforma->id] = count($filter);
    
            $count[$campana->id]['total'] += $count[$campana->id][$plataforma->id];
            $count['total'][$plataforma->id] += $count[$campana->id][$plataforma->id];
          }
          $count['total']['total'] += $count[$campana->id]['total'];
    
          if($count[$campana->id]['total'] == 0){
            unset($campanas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorCampanasAndPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformas = Plataforma::findMany($params["plataformas"]);
        $campanasTotales = Campaign::findMany($params["campaigns"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countCampxPlat($campanasTotales, $plataformas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($campanasTotales) ;){
            $campanas = $campanasTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-campanas-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'campanasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataformas', 'campanas', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-campanas-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorCampanasAndPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorCampanasAndPlataformas($params);
    
        $tituloCorreo = "Reporte por Campañas";
    
        $defaultAsunto = "Reporte por Campañas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countCampxTipoNota(&$campanas, $tipoNotas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($campanas as $key => $campana) {
          # code...
          $count[$campana->id]['total'] = 0;
          foreach ($tipoNotas as $tipoNota) {
            # code...
            if(!isset($count['total'][$tipoNota])){
              $count['total'][$tipoNota] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($campana, $tipoNota)
            {
              return $campana->id == $item->idCampaign && $tipoNota == $item->tipoNota;
            });
    
            $count[$campana->id][$tipoNota] = count($filter);
    
            $count[$campana->id]['total'] += $count[$campana->id][$tipoNota];
            $count['total'][$tipoNota] += $count[$campana->id][$tipoNota];
          }
          $count['total']['total'] += $count[$campana->id]['total'];
    
          if($count[$campana->id]['total'] == 0){
            unset($campanas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorCampanasAndTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotas = $params["tipoNotas"];
        $campanasTotales = Campaign::findMany($params["campaigns"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countCampxTipoNota($campanasTotales, $tipoNotas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($campanasTotales) ;){
            $campanas = $campanasTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-campanas-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'campanasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNotas', 'campanas', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-campanas-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');

        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorCampanasAndTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorCampanasAndTipoNotas($params);
    
        $tituloCorreo = "Reporte por Campañas";
    
        $defaultAsunto = "Reporte por Campañas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countCampxTipoTier(&$campanas, $tipoTiers, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($campanas as $key => $campana) {
          # code...
          $count[$campana->id]['total'] = 0;
          foreach ($tipoTiers as $tipoTier) {
            # code...
            if(!isset($count['total'][$tipoTier])){
              $count['total'][$tipoTier] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($campana, $tipoTier)
            {
              return $campana->id == $item->idCampaign && $tipoTier == $item->tipoTier;
            });
    
            $count[$campana->id][$tipoTier] = count($filter);
    
            $count[$campana->id]['total'] += $count[$campana->id][$tipoTier];
            $count['total'][$tipoTier] += $count[$campana->id][$tipoTier];
          }
          $count['total']['total'] += $count[$campana->id]['total'];
    
          if($count[$campana->id]['total'] == 0){
            unset($campanas[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorCampanasAndTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiers = $params["tipoTiers"];
        $campanasTotales = Campaign::findMany($params["campaigns"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countCampxTipoTier($campanasTotales, $tipoTiers, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($campanasTotales) ;){
            $campanas = $campanasTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-campanas-tiers', compact('alcanceTotal', 'valorizadoTotal', 'campanasTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTiers', 'campanas', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-campanas-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorCampanas_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');

        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorCampanasAndTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorCampanasAndTipoTiers($params);
    
        $tituloCorreo = "Reporte por Campañas";
    
        $defaultAsunto = "Reporte por Campañas";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    /** REPORTE POR VOCEROS */

    private function countVocxPlat(&$voceros, $plataformas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($voceros as $key => $vocero) {
          # code...
          $count[$vocero->id]['total'] = 0;
          foreach ($plataformas as $plataforma) {
            # code...
            if(!isset($count['total'][$plataforma->id])){
              $count['total'][$plataforma->id] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($vocero, $plataforma)
            {
              return $vocero->id == $item->idVocero && $plataforma->id == $item->idPlataforma;
            });
    
            $count[$vocero->id][$plataforma->id] = count($filter);
    
            $count[$vocero->id]['total'] += $count[$vocero->id][$plataforma->id];
            $count['total'][$plataforma->id] += $count[$vocero->id][$plataforma->id];
          }
          $count['total']['total'] += $count[$vocero->id]['total'];
    
          if($count[$vocero->id]['total'] == 0){
            unset($voceros[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorVocerosAndPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformas = Plataforma::findMany($params["plataformas"]);
        $vocerosTotales = Persona::findMany($params["voceros"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countVocxPlat($vocerosTotales, $plataformas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($vocerosTotales) ;){
            $voceros = $vocerosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-voceros-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'vocerosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataformas', 'voceros', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-voceros-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorVocerosAndPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        
        $filename = $this->createAndSaveReportePorVocerosAndPlataformas($params);
    
        $tituloCorreo = "Reporte por Voceros";
    
        $defaultAsunto = "Reporte por Voceros";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countVocxTipoNota(&$voceros, $tipoNotas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($voceros as $key => $vocero) {
          # code...
          $count[$vocero->id]['total'] = 0;
          foreach ($tipoNotas as $tipoNota) {
            # code...
            if(!isset($count['total'][$tipoNota])){
              $count['total'][$tipoNota] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($vocero, $tipoNota)
            {
              return $vocero->id == $item->idVocero && $tipoNota == $item->tipoNota;
            });
    
            $count[$vocero->id][$tipoNota] = count($filter);
    
            $count[$vocero->id]['total'] += $count[$vocero->id][$tipoNota];
            $count['total'][$tipoNota] += $count[$vocero->id][$tipoNota];
          }
          $count['total']['total'] += $count[$vocero->id]['total'];
    
          if($count[$vocero->id]['total'] == 0){
            unset($voceros[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorVocerosAndTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotas = $params["tipoNotas"];
        $vocerosTotales = Persona::findMany($params["voceros"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countVocxTipoNota($vocerosTotales, $tipoNotas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($vocerosTotales) ;){
            $voceros = $vocerosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-voceros-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'vocerosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNotas', 'voceros', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-voceros-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);

        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorVocerosAndTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        
        $filename = $this->createAndSaveReportePorVocerosAndTipoNotas($params);
    
        $tituloCorreo = "Reporte por Voceros";
    
        $defaultAsunto = "Reporte por Voceros";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countVocxTipoTier(&$voceros, $tipoTiers, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($voceros as $key => $vocero) {
          # code...
          $count[$vocero->id]['total'] = 0;
          foreach ($tipoTiers as $tipoTier) {
            # code...
            if(!isset($count['total'][$tipoTier])){
              $count['total'][$tipoTier] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($vocero, $tipoTier)
            {
              return $vocero->id == $item->idVocero && $tipoTier == $item->tipoTier;
            });
    
            $count[$vocero->id][$tipoTier] = count($filter);
    
            $count[$vocero->id]['total'] += $count[$vocero->id][$tipoTier];
            $count['total'][$tipoTier] += $count[$vocero->id][$tipoTier];
          }
          $count['total']['total'] += $count[$vocero->id]['total'];
    
          if($count[$vocero->id]['total'] == 0){
            unset($voceros[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorVocerosAndTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiers = $params["tipoTiers"];
        $vocerosTotales = Persona::findMany($params["voceros"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countVocxTipoTier($vocerosTotales, $tipoTiers, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($vocerosTotales) ;){
            $voceros = $vocerosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-voceros-tiers', compact('alcanceTotal', 'valorizadoTotal', 'vocerosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTiers', 'voceros', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-voceros-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);

        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorVoceros_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorVocerosAndTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        
        $filename = $this->createAndSaveReportePorVocerosAndTipoTiers($params);
    
        $tituloCorreo = "Reporte por Voceros";
    
        $defaultAsunto = "Reporte por Voceros";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    /** REPORTE POR PLANES DE MEDIOS */

    private function countPlanxPlat(&$planMedios, $plataformas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($planMedios as $key => $planMedio) {
          # code...
          $count[$planMedio->id]['total'] = 0;
          foreach ($plataformas as $plataforma) {
            # code...
            if(!isset($count['total'][$plataforma->id])){
              $count['total'][$plataforma->id] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($planMedio, $plataforma)
            {
              return $planMedio->id == $item->idPlanMedio && $plataforma->id == $item->idPlataforma;
            });
    
            $count[$planMedio->id][$plataforma->id] = count($filter);
    
            $count[$planMedio->id]['total'] += $count[$planMedio->id][$plataforma->id];
            $count['total'][$plataforma->id] += $count[$planMedio->id][$plataforma->id];
          }
          $count['total']['total'] += $count[$planMedio->id]['total'];
    
          if($count[$planMedio->id]['total'] == 0){
            unset($planMedios[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlanMediosAndPlataformas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $plataformas = Plataforma::findMany($params["plataformas"]);
        $planMediosTotales = PlanMedio::findMany($params["planmedios"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlanxPlat($planMediosTotales, $plataformas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($planMediosTotales) ;){
            $planMedios = $planMediosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-planmedios-plataformas', compact('alcanceTotal', 'valorizadoTotal', 'planMediosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'plataformas', 'planMedios', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-planmedios-plataformas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');
    
        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlanMediosAndPlataformas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlanMediosAndPlataformas($params);
    
        $tituloCorreo = "Reporte por Planes de Medios";
    
        $defaultAsunto = "Reporte por Planes de Medios";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countPlanxTipoNota(&$planMedios, $tipoNotas, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($planMedios as $key => $planMedio) {
          # code...
          $count[$planMedio->id]['total'] = 0;
          foreach ($tipoNotas as $tipoNota) {
            # code...
            if(!isset($count['total'][$tipoNota])){
              $count['total'][$tipoNota] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($planMedio, $tipoNota)
            {
              return $planMedio->id == $item->idPlanMedio && $tipoNota == $item->tipoNota;
            });
    
            $count[$planMedio->id][$tipoNota] = count($filter);
    
            $count[$planMedio->id]['total'] += $count[$planMedio->id][$tipoNota];
            $count['total'][$tipoNota] += $count[$planMedio->id][$tipoNota];
          }
          $count['total']['total'] += $count[$planMedio->id]['total'];
    
          if($count[$planMedio->id]['total'] == 0){
            unset($planMedios[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlanMediosAndTipoNotas($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoNotas = $params["tipoNotas"];
        $planMediosTotales = PlanMedio::findMany($params["planmedios"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlanxTipoNota($planMediosTotales, $tipoNotas, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($planMediosTotales) ;){
            $planMedios = $planMediosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-planmedios-tiponotas', compact('alcanceTotal', 'valorizadoTotal', 'planMediosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoNotas', 'planMedios', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-planmedios-tiponotas', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');

        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlanMediosAndTipoNotas($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlanMediosAndTipoNotas($params);
    
        $tituloCorreo = "Reporte por Planes de Medios";
    
        $defaultAsunto = "Reporte por Planes de Medios";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

    private function countPlanxTipoTier(&$planMedios, $tipoTiers, $data)
    {
        $count = array();
    
        $count['total']['total'] = 0;
        foreach ($planMedios as $key => $planMedio) {
          # code...
          $count[$planMedio->id]['total'] = 0;
          foreach ($tipoTiers as $tipoTier) {
            # code...
            if(!isset($count['total'][$tipoTier])){
              $count['total'][$tipoTier] = 0;
            }
    
            $filter = $data->filter(function ($item) use ($planMedio, $tipoTier)
            {
              return $planMedio->id == $item->idPlanMedio && $tipoTier == $item->tipoTier;
            });
    
            $count[$planMedio->id][$tipoTier] = count($filter);
    
            $count[$planMedio->id]['total'] += $count[$planMedio->id][$tipoTier];
            $count['total'][$tipoTier] += $count[$planMedio->id][$tipoTier];
          }
          $count['total']['total'] += $count[$planMedio->id]['total'];
    
          if($count[$planMedio->id]['total'] == 0){
            unset($planMedios[$key]);
          }
        }
    
        return $count;
    }

    private function createAndSaveReportePorPlanMediosAndTipoTiers($params)
    {
        $info = $this->getDataForReporteV3($params);
        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($params["idCliente"]);
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
        $tipoTiers = $params["tipoTiers"];
        $planMediosTotales = PlanMedio::findMany($params["planmedios"]);
    
        $isVal = $params['isVal'];
        $isDet = $params['isDet'];
    
        $count = $this->countPlanxTipoTier($planMediosTotales, $tipoTiers, $data);
    
        $mpdf = new \Mpdf\Mpdf();
    
        $tmpDirectoryName = 'tmpPDF_'.time();
        Storage::makeDirectory('public/'.$tmpDirectoryName);
    
        $printFirstPage = true;
        $lastPage = false;
        for($i = 0 ; $i < count($planMediosTotales) ;){
            $planMedios = $planMediosTotales->slice($i, 5)->values();
    
            $view =  \View::make('pdf.reporte-planmedios-tiers', compact('alcanceTotal', 'valorizadoTotal', 'planMediosTotales', 'printFirstPage', 'lastPage', 'cliente', 'fechaInicio', 'fechaFin', 'tipoTiers', 'planMedios', 'data', 'isVal', 'isDet', 'count'))->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadHTML($view);
            $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
            $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
            for ($page = 1; $page <= $pageCount; $page++) {
              $mpdf->AddPage();
              $template = $mpdf->importPage($page);
              $mpdf->useTemplate($template);
            }
    
            $printFirstPage = false;
    
            $i = $i + 5;
        }
    
        $lastPage = true;

        $view =  \View::make('pdf.reporte-planmedios-tiers', compact('lastPage'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadHTML($view);
        $filename = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.Str::random(8).'.pdf';
        $pdf->save(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        $pageCount = $mpdf->setSourceFile(storage_path('app/public/'.$tmpDirectoryName.'/') . $filename);
    
        for ($page = 1; $page <= $pageCount; $page++) {
          $mpdf->AddPage();
          $template = $mpdf->importPage($page);
          $mpdf->useTemplate($template);
        }
    
        $filenameMerge = 'ReportePorPlanMedios_'.$cliente->nombreComercial.'_'.date_format(date_create($fechaInicio), 'd-m-Y').'_'.date_format(date_create($fechaFin), 'd-m-Y').'_'.time().'.pdf';
        $mpdf->Output(storage_path('app/public/') . $filenameMerge, 'F');

        //Herramienta::storeReporteFile($filenameMerge);
        $reporte = Reporte::create([
            'nameReporte' => $filenameMerge,
            'createdDate' => date('Y-m-d'),
        ]);
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

    private function sendReportePorPlanMediosAndTipoTiers($params) 
    {

        $usuario = User::find(auth()->user()->id);
    
        $fechaInicio = $params["fechaInicio"];
        $fechaFin = $params["fechaFin"];
    
        $filename = $this->createAndSaveReportePorPlanMediosAndTipoTiers($params);
    
        $tituloCorreo = "Reporte por Planes de Medios";
    
        $defaultAsunto = "Reporte por Planes de Medios";
        $asunto = empty($params["asunto"]) ? $defaultAsunto : $params["asunto"]; 
    
        $destinatarios = $params["destinatarios"];
    
        $mensaje = $params["mensaje"];
    
        $emailsNotExist = array();
        $failDestinatarios = array();
        $successDestinatarios = array();
    
        // Envio de reporte por correo a los destinatarios
        foreach ($destinatarios as $destinatario) {
    
            $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                array_push($emailsNotExist,$destinatario);
            }else{
                $tries = 0;
                $mailSent = false;
        
                while(!$mailSent && ($tries<3)){
        
                    try {
            
                        Mail::to($destinatario['email'])
                            ->send(new ReporteDestinatarioMail($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto));
            
                        $mailSent = true;
            
                    } catch (\Exception $e) {
            
                    }
            
                    $tries++;
        
                }
        
                if($mailSent){
                    array_push($successDestinatarios,$destinatario);
                }else{
                    array_push($failDestinatarios,$destinatario);
                }
            }
    
        }
    
        //$cc = $data["cc"];
        //$cco = $data["cco"];
        $cc = array();
        $cco = array();
    
        $ccEmails = array_map(function ($oneCC)
        {
          return $oneCC['email'];
        }, $cc);
    
        $ccoEmails = array_map(function ($oneCCO)
        {
          return $oneCCO['email'];
        }, $cco);
    
        // Envio de correo al remitente
        /*Mail::to($usuario['email'])
            ->cc($ccEmails)
            ->bcc($ccoEmails)
            ->send(new ReporteRemitenteEmail($tituloCorreo, $usuario, $successDestinatarios, $failDestinatarios, $emailsNotExist, $fechaInicio, $fechaFin, $filename, $subject));*/
    
    }

}
