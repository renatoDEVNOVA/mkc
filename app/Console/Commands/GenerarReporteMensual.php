<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Reporte;
use App\Cliente;
use App\Plataforma;
use App\Campaign;
use App\ClienteVocero;
use App\Persona;
use App\DetallePlanMedio;
use App\DetallePlanResultadoPlataforma;
use Log;
use Validator;
use Storage;
use DB;
use Crypt;
use Illuminate\Support\Str;

use Mail;
use App\Mail\ReporteMensual as ReporteMensualMail;

class GenerarReporteMensual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:reporte_mensual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera los reportes mensuales';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $hourCurrent = date('G');

        $date = strtotime('-1 month');
        $M = date('m', $date);
        $Y = date('Y', $date);

        $fechaInicio = date('Y-m-01', $date);
        $fechaFin = date('Y-m-t', $date);

        Log::info('Mes: '.$M);
        Log::info('Año: '.$Y);
        Log::info('Desde: '.$fechaInicio);
        Log::info('Hasta: '.$fechaFin);

        $clientes = Cliente::all();
        
        foreach ($clientes as $cliente) {
            # code...
            $clienteEnvio = $cliente->envios()->where('tipoPeriodo', 2)->first();

            $destinatarios = $cliente->users()->where('sendAuto', 1)->get();
            Log::info('Contador Destinatarios: '.count($destinatarios));

            if(!empty($clienteEnvio) && (count($destinatarios) > 0)){
                Log::info('Cliente: '.$cliente->nombreComercial);

                if($clienteEnvio->horaEnvio == $hourCurrent){
                    Log::info('Inicio GenerateMonthly');

                    $tiposReporte = explode(',', $clienteEnvio->tiposReporte);

                    $filesName = array();
                    $emptyData = array();

                    if (in_array(1, $tiposReporte)) {
                        $info = $this->getDataForReporteGenerate($cliente->id, $fechaInicio, $fechaFin, 1);
                        $data = $info['data'];
                        Log::info('Contador Data Plataformas: '.count($data));
                        if(count($data) > 0){
                            Log::info('Inicio generateReportePorPlataformas');
                            $fileNamePlataformas = $this->generateReportePorPlataformas($cliente->id, $fechaInicio, $fechaFin, $info);
                            Log::info('Fin generateReportePorPlataformas');

                            $reporte = Reporte::create([
                                'nameReporte' => $fileNamePlataformas,
                                'createdDate' => date('Y-m-d'),
                                'cliente_id' => $cliente->id,
                                'tipoPeriodo' => 2,
                                'numPeriodo' => $M,
                                'year' => $Y,
                                'tipoReporte' => 1,
                            ]);

                            $filesName[1] = $fileNamePlataformas;
                            $emptyData[1] = false;
                        }else{
                            Log::info('Data Vacia Plataformas');
                            $emptyData[1] = true;
                        }
                    }

                    if (in_array(2, $tiposReporte)) {
                        $info = $this->getDataForReporteGenerate($cliente->id, $fechaInicio, $fechaFin, 2);
                        $data = $info['data'];
                        Log::info('Contador Data Campanas: '.count($data));
                        if(count($data) > 0){
                            Log::info('Inicio generateReportePorCampanasAndPlataformas');
                            $fileNameCampanas = $this->generateReportePorCampanasAndPlataformas($cliente->id, $fechaInicio, $fechaFin, $info);
                            Log::info('Fin generateReportePorCampanasAndPlataformas');

                            $reporte = Reporte::create([
                                'nameReporte' => $fileNameCampanas,
                                'createdDate' => date('Y-m-d'),
                                'cliente_id' => $cliente->id,
                                'tipoPeriodo' => 2,
                                'numPeriodo' => $M,
                                'year' => $Y,
                                'tipoReporte' => 2,
                            ]);

                            $filesName[2] = $fileNameCampanas;
                            $emptyData[2] = false;
                        }else{
                            Log::info('Data Vacia Campanas');
                            $emptyData[2] = true;
                        }
                    }

                    if (in_array(3, $tiposReporte)) {
                        $info = $this->getDataForReporteGenerate($cliente->id, $fechaInicio, $fechaFin, 3);
                        $data = $info['data'];
                        Log::info('Contador Data Voceros: '.count($data));
                        if(count($data) > 0){
                            Log::info('Inicio generateReportePorVocerosAndPlataformas');
                            $fileNameVoceros = $this->generateReportePorVocerosAndPlataformas($cliente->id, $fechaInicio, $fechaFin, $info);
                            Log::info('Fin generateReportePorVocerosAndPlataformas');

                            $reporte = Reporte::create([
                                'nameReporte' => $fileNameVoceros,
                                'createdDate' => date('Y-m-d'),
                                'cliente_id' => $cliente->id,
                                'tipoPeriodo' => 2,
                                'numPeriodo' => $M,
                                'year' => $Y,
                                'tipoReporte' => 3,
                            ]);

                            $filesName[3] = $fileNameVoceros;
                            $emptyData[3] = false;
                        }else{
                            Log::info('Data Vacia Voceros');
                            $emptyData[3] = true;
                        }
                    }

                    Log::info('Fin GenerateMonthly');


                    Log::info('Inicio SendMonthly');

                    $emailsNotExist = array();
                    $failDestinatarios = array();
                    $successDestinatarios = array();

                    $mesCadena = array(
                      1 => "ENERO",
                      2 => "FEBRERO",
                      3 => "MARZO",
                      4 => "ABRIL",
                      5 => "MAYO",
                      6 => "JUNIO",
                      7 => "JULIO",
                      8 => "AGOSTO",
                      9 => "SEPTIEMBRE",
                      10 => "OCTUBRE",
                      11 => "NOVIEMBRE",
                      12 => "DICIEMBRE",
                    );

                    $Mes = $mesCadena[date('n', $date)];

                    // Envio de reporte por correo a los destinatarios
                    foreach ($destinatarios as $destinatario) {

                        $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                            'email' => ['email'],
                        ]);
            

                        if ($validatorEmail->fails()) {
                            array_push($emailsNotExist,$destinatario);
                        }else{

                            if (in_array(1, $tiposReporte)) {

                                if(!$emptyData[1]){
    
                                    Log::info('Send Plataformas');
        
                                    $tituloCorreo = "REPORTE MENSUAL POR PLATAFORMAS";
                                    $asunto = strtoupper($cliente->nombreComercial).": REPORTE MENSUAL POR PLATAFORMAS - ".$Mes;
                                    $fileName = $filesName[1];
        
                                    try {
        
                                        Mail::to($destinatario->email, $destinatario->name)
                                        ->send(new ReporteMensualMail($tituloCorreo, $destinatario, $fileName, $Mes, $fechaInicio, $fechaFin, $asunto));
        
                                    } catch (\Exception $e) {
        
                                    }
    
                                }
                            }
      
                            if (in_array(2, $tiposReporte)) {
    
                                if(!$emptyData[2]){
    
                                    Log::info('Send Campanas');
        
                                    $tituloCorreo = "REPORTE MENSUAL POR CAMPAÑAS";
                                    $asunto = strtoupper($cliente->nombreComercial).": REPORTE MENSUAL POR CAMPAÑAS - ".$Mes;
                                    $fileName = $filesName[2];
        
                                    try {
        
                                        Mail::to($destinatario->email, $destinatario->name)
                                        ->send(new ReporteMensualMail($tituloCorreo, $destinatario, $fileName, $Mes, $fechaInicio, $fechaFin, $asunto));
        
                                    } catch (\Exception $e) {
        
                                    }
    
                                }
                            }
      
                            if (in_array(3, $tiposReporte)) {
      
                                if(!$emptyData[3]){
      
                                    Log::info('Send Voceros');
        
                                    $tituloCorreo = "REPORTE MENSUAL POR VOCEROS";
                                    $asunto = strtoupper($cliente->nombreComercial).": REPORTE MENSUAL POR VOCEROS - ".$Mes;
                                    $fileName = $filesName[3];
        
                                    try {
        
                                        Mail::to($destinatario->email, $destinatario->name)
                                        ->send(new ReporteMensualMail($tituloCorreo, $destinatario, $fileName, $Mes, $fechaInicio, $fechaFin, $asunto));
        
                                    } catch (\Exception $e) {
        
                                    }
      
                                }
                            }

                        }
                    }

                    Log::info('Fin SendMonthly');
                }
            }
            
        }

        return 0;
    }

    private function getPathDir($idDetallePlanMedio)
    {
        $DPM = DetallePlanMedio::find($idDetallePlanMedio);
        $idDPM = $DPM->vinculado ? $DPM->idDetallePlanMedioPadre : $DPM->id;
        $detallePlanMedio = DetallePlanMedio::find($idDPM);

        $clienteAlias = $detallePlanMedio->planMedio->campaign->cliente->alias;
        $campaignAlias = $detallePlanMedio->planMedio->campaign->alias;
        $idPM = $detallePlanMedio->planMedio->id;

        return "${clienteAlias}/${campaignAlias}/pm{$idPM}";
    }

    private function getDataForReporteGenerate($idCliente, $fechaInicio, $fechaFin, $tipoReporte)
    {
      switch ($tipoReporte) {
          case 1:
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
              ->whereNull('resultado_plataformas.deleted_at')
              ->whereNull('detalle_plan_medios.deleted_at')
              ->where('campaigns.cliente_id', $idCliente)
              ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
              ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
              ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
              ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
              ->get();
              break;
    
          case 2:
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
              ->whereNull('resultado_plataformas.deleted_at')
              ->whereNull('detalle_plan_medios.deleted_at')
              ->where('campaigns.cliente_id', $idCliente)
              ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
              ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
              ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
              ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
              ->get();
              break;
          
          case 3:
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
              ->whereNull('resultado_plataformas.deleted_at')
              ->whereNull('detalle_plan_medios.deleted_at')
              ->whereNull('detalle_plan_medio_vocero.deleted_at')
              ->where('campaigns.cliente_id', $idCliente)
              ->where('resultado_plataformas.fechaPublicacion', '>=', $fechaInicio)
              ->where('resultado_plataformas.fechaPublicacion', '<=', $fechaFin)
              ->orderBy('resultado_plataformas.fechaPublicacion', 'asc')
              ->orderBy('detalle_plan_resultado_plataformas.id', 'asc')
              ->get();
              break;
          
          default:
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

    private function generateReportePorPlataformas($idCliente, $fechaInicio, $fechaFin, $info)
    {

        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($idCliente);
        $plataformasTotales = Plataforma::all();
    
        $isVal = false;
        $isDet = false;
    
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
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

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

    private function generateReportePorCampanasAndPlataformas($idCliente, $fechaInicio, $fechaFin, $info)
    {

        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
       
        $cliente = Cliente::find($idCliente);
        $plataformas = Plataforma::all();
        $campanasTotales = Campaign::where('cliente_id', $idCliente)->get();
    
        $isVal = false;
        $isDet = false;
    
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
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }

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

    private function generateReportePorVocerosAndPlataformas($idCliente, $fechaInicio, $fechaFin, $info)
    {

        $data = $info['data'];
        $alcanceTotal = $info['alcanceTotal'];
        $valorizadoTotal = $info['valorizadoTotal'];
    
        $cliente = Cliente::find($idCliente);
        $plataformas = Plataforma::all();

        $idsVocero = ClienteVocero::select("idVocero")
        ->where("cliente_id", $idCliente)->distinct()->get()
        ->map(function($item){return $item->idVocero;});

        $vocerosTotales = Persona::findMany($idsVocero);
    
        $isVal = false;
        $isDet = false;
    
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
    
        unset($mpdf);
        gc_collect_cycles();
        Storage::deleteDirectory('public/'.$tmpDirectoryName);
    
        return $filenameMerge;
    }
}
