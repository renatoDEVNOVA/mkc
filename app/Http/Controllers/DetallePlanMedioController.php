<?php

namespace App\Http\Controllers;

use App\DetallePlanMedio;
use Illuminate\Http\Request;

use App\DetallePlanResultadoPlataforma;
use App\ProgramaContacto;
use App\PlanMedio;
use App\Bitacora;
use App\MedioPlataforma;
use App\ProgramaPlataforma;
use App\Etiqueta;
use App\Campaign;
use App\Http\Resources\DetallePlanMedio\DetallePlanMedioCollection;
use App\Http\Resources\DetallePlanMedio\DetallePlanMedioResource;
use App\ResultadoPlataforma;

use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

use Validator;
use DB;

class DetallePlanMedioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $detallePlanMedios = DetallePlanMedio::with(
            'planMedio.campaign.cliente',
        )->get();

        return response()->json([
            'ready' => true,
            'detallePlanMedios' => $detallePlanMedios,
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

    private function idsMedioPlataformaByProgramaContacto($idProgramaContacto){
        return ProgramaContacto::find($idProgramaContacto)->idsMedioPlataforma;
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
                'idPlanMedio.required' => 'El Plan de Medio es obligatoria.',
                'idPlanMedio.exists' => 'Seleccione una Plan de Medio valida.',
                'idProgramaContacto.required' => 'Contacto/Programa es obligatorio.',
                'idProgramaContacto.exists' => 'Selecione Contacto/Programa valido.',
                'idProgramaContacto.unique' => 'Ya existe un registro con el Contacto/Programa deseado.',
                'idsMedioPlataforma.required' => 'Plataformas es obligatorio.',
                'idsMedioPlataforma.*.in' => 'Seleccione plataformas validas.',
                'tipoTier.required' => 'Tier es obligatorio.',
                'tipoTier.in' => 'Selecione Tier valido.',
                'tipoNota.required' => 'Tipo de Nota es obligatorio.',
                'tipoNota.in' => 'Selecione Tipo de Nota valido.',
                'tipoEtapa.required' => 'Etapa es obligatorio.',
                'tipoEtapa.in' => 'Selecione Etapa valido.',
                'voceros.required' => 'Voceros es obligatorio.',
                'voceros.*.exists' => 'Seleccione voceros validas.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'idProgramaContacto' => [
                    'required',
                    'exists:programa_contactos,id',
                    /*Rule::unique('detalle_plan_medios')->where(function ($query) use ($request){
                        return $query->where('idPlanMedio', $request->idPlanMedio)->whereNull('deleted_at');
                    }),*/
                ],
                'idsMedioPlataforma' => ['required','array'],
                'idsMedioPlataforma.*' =>['in:'.$this->idsMedioPlataformaByProgramaContacto($request->idProgramaContacto)],
                'tipoTier' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'tipoNota' => [
                    'required',
                    Rule::in([1, 2, 3, 4]),
                ],
                'tipoEtapa' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'muestrasRegistradas' => ['required','integer'],
                'muestrasEnviadas' => ['required','integer'],
                'muestrasVerificadas' => ['required','integer'],
                'voceros' => ['required','array'],
                'voceros.*' => [
                    Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($request){
                        $planMedio = PlanMedio::find($request->idPlanMedio);
                        $query->where('campaign_id', $planMedio->campaign_id);
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
            $planMedio = PlanMedio::find($request->idPlanMedio);

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un DPM para el plan de medios deseado',
                ], 400);
            }

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin']) && $planMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un DPM para el plan de medios deseado',
                ], 400);
            }*/

            if (!(($request->muestrasRegistradas >= $request->muestrasEnviadas) && ($request->muestrasEnviadas >= $request->muestrasVerificadas))) { 
                return response()->json([
                    'ready' => false,
                    'message' => 'Cantidades de muestras no validas',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'idPlanMedio' => $request->idPlanMedio,
                'idProgramaContacto' => $request->idProgramaContacto,
                'idsMedioPlataforma' => implode(',', $request->idsMedioPlataforma),
                'tipoTier' => $request->tipoTier,
                'tipoNota' => $request->tipoNota,
                'tipoEtapa' => $request->tipoEtapa,
                'muestrasRegistradas' => $request->muestrasRegistradas,
                'muestrasEnviadas' => $request->muestrasEnviadas,
                'muestrasVerificadas' => $request->muestrasVerificadas,
                'statusPublicado' => 0,
                'statusExperto' => 0,
                'vinculado' => 0,
                'idDetallePlanMedioPadre' => null,
                'user_id' => $planMedio->user_id,
            );

            $detallePlanMedio = DetallePlanMedio::create($data);

            if (!$detallePlanMedio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha creado',
                ], 500);
            }

            if (isset($request->voceros)) {

                $detallePlanMedio->voceros()->sync($request->voceros);

            }

            $bitacora = $this->generateBitacora($detallePlanMedio->id);
            if (!$bitacora->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear la bitacora',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha creado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function storeV2(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idPlanMedio.required' => 'El Plan de Medio es obligatoria.',
                'idPlanMedio.exists' => 'Seleccione una Plan de Medio valida.',
                'idProgramaContacto.required' => 'Contacto/Programa es obligatorio.',
                'idProgramaContacto.exists' => 'Selecione Contacto/Programa valido.',
                'idProgramaContacto.unique' => 'Ya existe un registro con el Contacto/Programa deseado.',
                'idsMedioPlataforma.required' => 'Plataformas es obligatorio.',
                'idsMedioPlataforma.*.in' => 'Seleccione plataformas validas.',
                'tipoTier.required' => 'Tier es obligatorio.',
                'tipoTier.in' => 'Selecione Tier valido.',
                'tipoNota.required' => 'Tipo de Nota es obligatorio.',
                'tipoNota.in' => 'Selecione Tipo de Nota valido.',
                'tipoEtapa.required' => 'Etapa es obligatorio.',
                'tipoEtapa.in' => 'Selecione Etapa valido.',
                'voceros.required' => 'Voceros es obligatorio.',
                'voceros.*.exists' => 'Seleccione voceros validas.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'idProgramaContacto' => [
                    'required',
                    'exists:programa_contactos,id',
                    /*Rule::unique('detalle_plan_medios')->where(function ($query) use ($request){
                        return $query->where('idPlanMedio', $request->idPlanMedio)->whereNull('deleted_at');
                    }),*/
                ],
                'idsMedioPlataforma' => ['required','array'],
                'idsMedioPlataforma.*' =>['in:'.$this->idsMedioPlataformaByProgramaContacto($request->idProgramaContacto)],
                'tipoTier' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'tipoNota' => [
                    'required',
                    Rule::in([1, 2, 3, 4]),
                ],
                'tipoEtapa' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'muestrasRegistradas' => ['required','integer'],
                'muestrasEnviadas' => ['required','integer'],
                'muestrasVerificadas' => ['required','integer'],
                'observacion' => ['required'],
                'voceros' => ['required','array'],
                'voceros.*' => [
                    Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($request){
                        $planMedio = PlanMedio::find($request->idPlanMedio);
                        $query->where('campaign_id', $planMedio->campaign_id);
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
            $planMedio = PlanMedio::find($request->idPlanMedio);

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un DPM para el plan de medios deseado',
                ], 400);
            }

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin']) && $planMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un DPM para el plan de medios deseado',
                ], 400);
            }*/

            if (!(($request->muestrasRegistradas >= $request->muestrasEnviadas) && ($request->muestrasEnviadas >= $request->muestrasVerificadas))) { 
                return response()->json([
                    'ready' => false,
                    'message' => 'Cantidades de muestras no validas',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'idPlanMedio' => $request->idPlanMedio,
                'idProgramaContacto' => $request->idProgramaContacto,
                'idsMedioPlataforma' => implode(',', $request->idsMedioPlataforma),
                'tipoTier' => $request->tipoTier,
                'tipoNota' => $request->tipoNota,
                'tipoEtapa' => $request->tipoEtapa,
                'muestrasRegistradas' => $request->muestrasRegistradas,
                'muestrasEnviadas' => $request->muestrasEnviadas,
                'muestrasVerificadas' => $request->muestrasVerificadas,
                'statusPublicado' => 0,
                'statusExperto' => 0,
                'vinculado' => 0,
                'idDetallePlanMedioPadre' => null,
                'user_id' => $planMedio->user_id,
            );

            $detallePlanMedio = DetallePlanMedio::create($data);

            if (!$detallePlanMedio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha creado',
                ], 500);
            }

            if (isset($request->voceros)) {

                $detallePlanMedio->voceros()->sync($request->voceros);

            }

            $bitacora = $this->generateBitacoraV2($detallePlanMedio->id, $request->observacion);
            if (!$bitacora->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear la bitacora',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha creado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    private function generateBitacora($idDetallePlanMedio) {

        $data = array(
            'idDetallePlanMedio' => $idDetallePlanMedio,
            'tipoBitacora' => 1,
            'estado' => 1,
            'observacion' => 'Nuevo',
            'idTipoComunicacion' => null,
            'user_id' => auth()->user()->id,
            'idUserExtra' => null,
        );

        $bitacora = Bitacora::create($data);

        return $bitacora;
    }

    private function generateBitacoraV2($idDetallePlanMedio, $observacion) 
    {

        $data = array(
            'idDetallePlanMedio' => $idDetallePlanMedio,
            'tipoBitacora' => 1,
            'estado' => 1,
            'observacion' => $observacion,
            'idTipoComunicacion' => null,
            'user_id' => auth()->user()->id,
            'idUserExtra' => null,
        );

        $bitacora = Bitacora::create($data);

        return $bitacora;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DetallePlanMedio  $detallePlanMedio
     * @return \Illuminate\Http\Response
     */
    public function show(DetallePlanMedio $detallePlanMedio)
    {
        //
        if(is_null($detallePlanMedio)){
            return response()->json([
                'ready' => false,
                'message' => 'Detalle de plan de medio no encontrado',
            ], 404);
        }else{

            $idsCampaign = auth()->user()->myCampaigns();

            $detallePlanMedio->user;
            $detallePlanMedio->planMedio->campaign->cliente;
            $detallePlanMedio->programaContacto->programa->medio;
            $programa_id = $detallePlanMedio->programaContacto->programa->id;
            $detallePlanMedio->programaContacto->contacto;
            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
            $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma) use ($programa_id){
                $medioPlataforma->programaPlataforma = ProgramaPlataforma::where('programa_id', $programa_id)->where('idMedioPlataforma', $medioPlataforma->id)->first();
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });
            $detallePlanMedio->voceros;
            $detallePlanMedio->bitacoras = $detallePlanMedio->bitacoras()->get()->map(function($bitacora){
                $bitacora->user;
                $bitacora->userExtra;
                $bitacora->tipoComunicacion;
                return $bitacora;
            });
            $detallePlanMedio->detallePlanMedioPadre;
            /*if($detallePlanMedio->vinculado){
                $detallePlanMedio->detallePlanMedioPadre = $this->getDetallePlanMedioPadre($detallePlanMedio->idDetallePlanMedioPadre);
            }*/
            $detallePlanMedio->resultados;
            $detallePlanMedio->isEditable = in_array($detallePlanMedio->planMedio->campaign_id, $idsCampaign);

            return response()->json([
                'ready' => true,
                'detallePlanMedio' => $detallePlanMedio,
            ]);
        }
    }

    private function getDetallePlanMedioPadre($idDetallePlanMedioPadre)
    {
        $detallePlanMedioPadre = DetallePlanMedio::find($idDetallePlanMedioPadre);
        $detallePlanMedioPadre->planMedio->campaign->cliente;
        $detallePlanMedioPadre->programaContacto->programa->medio;
        $programa_id = $detallePlanMedioPadre->programaContacto->programa->id;
        $detallePlanMedioPadre->programaContacto->contacto;
        $idsMedioPlataforma = explode(',', $detallePlanMedioPadre->idsMedioPlataforma);
        $detallePlanMedioPadre->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma) use ($programa_id){
            $medioPlataforma->programaPlataforma = ProgramaPlataforma::where('programa_id', $programa_id)->where('idMedioPlataforma', $medioPlataforma->id)->first();
            $medioPlataforma->plataformaClasificacion->plataforma;
            return $medioPlataforma;
        });
        $detallePlanMedioPadre->voceros;
        $detallePlanMedioPadre->bitacoras = $detallePlanMedioPadre->bitacoras()->get()->map(function($bitacora){
            $bitacora->user;
            $bitacora->tipoComunicacion;
            return $bitacora;
        });
        $detallePlanMedioPadre->resultados;

        return $detallePlanMedioPadre;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DetallePlanMedio  $detallePlanMedio
     * @return \Illuminate\Http\Response
     */
    public function edit(DetallePlanMedio $detallePlanMedio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DetallePlanMedio  $detallePlanMedio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DetallePlanMedio $detallePlanMedio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idProgramaContacto.required' => 'Contacto/Programa es obligatorio.',
                'idProgramaContacto.exists' => 'Selecione Contacto/Programa valido.',
                'idProgramaContacto.unique' => 'Ya existe un registro con el Contacto/Programa deseado.',
                'idsMedioPlataforma.required' => 'Plataformas es obligatorio.',
                'idsMedioPlataforma.*.in' => 'Seleccione plataformas validas.',
                'tipoTier.required' => 'Tier es obligatorio.',
                'tipoTier.in' => 'Selecione Tier valido.',
                'tipoNota.required' => 'Tipo de Nota es obligatorio.',
                'tipoNota.in' => 'Selecione Tipo de Nota valido.',
                'tipoEtapa.required' => 'Etapa es obligatorio.',
                'tipoEtapa.in' => 'Selecione Etapa valido.',
                'voceros.required' => 'Voceros es obligatorio.',
                'voceros.*.exists' => 'Seleccione voceros validas.',
            ];

            $validator = Validator::make($request->all(), [
                'idProgramaContacto' => [
                    'required',
                    'exists:programa_contactos,id',
                    /*Rule::unique('detalle_plan_medios')->ignore($detallePlanMedio->id)->where(function ($query) use ($detallePlanMedio){
                        return $query->where('idPlanMedio', $detallePlanMedio->idPlanMedio)->whereNull('deleted_at');
                    }),*/
                ],
                'idsMedioPlataforma' => ['required','array'],
                'idsMedioPlataforma.*' =>['in:'.$this->idsMedioPlataformaByProgramaContacto($request->idProgramaContacto)],
                'tipoTier' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'tipoNota' => [
                    'required',
                    Rule::in([1, 2, 3, 4]),
                ],
                'tipoEtapa' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'muestrasRegistradas' => ['required','integer'],
                'muestrasEnviadas' => ['required','integer'],
                'muestrasVerificadas' => ['required','integer'],
                'voceros' => ['required','array'],
                'voceros.*' => [
                    Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($detallePlanMedio){
                        $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
                        $query->where('campaign_id', $planMedio->campaign_id);
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
            $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign) && ($detallePlanMedio->user_id != auth()->user()->id)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el DPM',
                ], 400);
            }

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin']) && $detallePlanMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el DPM',
                ], 400);
            }*/

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin']) && $planMedio->user_id != auth()->user()->id && $detallePlanMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el DPM',
                ], 400);
            }*/

            $statusMessage = array(
                0 => 'EN PROCESO',
                1 => 'PUBLICADO',
                2 => 'RECHAZADO',
                3 => 'CANCELADO',
            );

            /*if($detallePlanMedio->statusPublicado != 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de actualizar el registro. Su estado actual es '.$statusMessage[$detallePlanMedio->statusPublicado],
                ], 400);
            }*/

            if (!(($request->muestrasRegistradas >= $request->muestrasEnviadas) && ($request->muestrasEnviadas >= $request->muestrasVerificadas))) { 
                return response()->json([
                    'ready' => false,
                    'message' => 'Cantidades de muestras no validas',
                ], 400);
            }

            // Datos Obligatorios
            /*if(($detallePlanMedio->statusPublicado == 0) && (!$detallePlanMedio->vinculado)){
                $detallePlanMedio->idProgramaContacto = $request->idProgramaContacto;
                $detallePlanMedio->idsMedioPlataforma = implode(',', $request->idsMedioPlataforma);
            }*/
            if(!$detallePlanMedio->vinculado){
                $detallePlanMedio->idsMedioPlataforma = implode(',', $request->idsMedioPlataforma);
                if($detallePlanMedio->statusPublicado == 0){
                    $detallePlanMedio->idProgramaContacto = $request->idProgramaContacto;
                }
            }
            $detallePlanMedio->tipoTier = $request->tipoTier;
            $detallePlanMedio->tipoNota = $request->tipoNota;
            $detallePlanMedio->tipoEtapa = $request->tipoEtapa;
            $detallePlanMedio->muestrasRegistradas = $request->muestrasRegistradas;
            $detallePlanMedio->muestrasEnviadas = $request->muestrasEnviadas;
            $detallePlanMedio->muestrasVerificadas = $request->muestrasVerificadas;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha actualizado',
                ], 500);
            }

            // Actualizamos los DPMs vinculados
            /*if(($detallePlanMedio->statusPublicado == 0) && (!$detallePlanMedio->vinculado)){

                $detallePlanMedioVinculados = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->get();

                foreach ($detallePlanMedioVinculados as $detallePlanMedioVinculado) {
                    # code...
                    $detallePlanMedioVinculado->idProgramaContacto = $request->idProgramaContacto;
                    $detallePlanMedioVinculado->idsMedioPlataforma = implode(',', $request->idsMedioPlataforma);
                    if (!$detallePlanMedioVinculado->save()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El detalle de plan de medio no se ha actualizado',
                        ], 500);
                    }
                }
            }*/
            if(!$detallePlanMedio->vinculado){

                $detallePlanMedioVinculados = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->get();

                foreach ($detallePlanMedioVinculados as $detallePlanMedioVinculado) {
                    # code...
                    if($detallePlanMedio->statusPublicado == 0){
                        $detallePlanMedioVinculado->idProgramaContacto = $request->idProgramaContacto;
                    }
                    $detallePlanMedioVinculado->idsMedioPlataforma = implode(',', $request->idsMedioPlataforma);
                    if (!$detallePlanMedioVinculado->save()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El detalle de plan de medio no se ha actualizado',
                        ], 500);
                    }
                }
            }

            if (isset($request->voceros)) {

                $detallePlanMedio->voceros()->sync($request->voceros);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha actualizado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
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
     * @param  \App\DetallePlanMedio  $detallePlanMedio
     * @return \Illuminate\Http\Response
     */
    public function destroy(DetallePlanMedio $detallePlanMedio)
    {
        //
        try {
            DB::beginTransaction();

            $statusMessage = array(
                0 => 'EN PROCESO',
                1 => 'PUBLICADO',
                2 => 'RECHAZADO',
                3 => 'CANCELADO',
            );

            if($detallePlanMedio->statusPublicado != 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. Su estado actual es '.$statusMessage[$detallePlanMedio->statusPublicado],
                ], 400);
            }

            if($detallePlanMedio->vinculado){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El DPM esta vinculado a otro DPM.',
                ], 400);
            }

            $hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();

            if($hasAssociated){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El DPM se encuentra relacionado con otros DPM.',
                ], 400);
            }

            Bitacora::where('idDetallePlanMedio', $detallePlanMedio->id)->delete();

            if (!$detallePlanMedio->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha eliminado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function associateDPM(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idPlanMedio.required' => 'El Plan de Medio es obligatoria.',
                'idPlanMedio.exists' => 'Seleccione una Plan de Medio valida.',
                'tipoTier.required' => 'Tier es obligatorio.',
                'tipoTier.in' => 'Selecione Tier valido.',
                'tipoNota.required' => 'Tipo de Nota es obligatorio.',
                'tipoNota.in' => 'Selecione Tipo de Nota valido.',
                'tipoEtapa.required' => 'Etapa es obligatorio.',
                'tipoEtapa.in' => 'Selecione Etapa valido.',
                'voceros.required' => 'Voceros es obligatorio.',
                'voceros.*.exists' => 'Seleccione voceros validas.',
            ];

            $validator = Validator::make($request->all(), [
                'idDetallePlanMedioPadre' => ['required','exists:detalle_plan_medios,id'],
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'tipoTier' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'tipoNota' => [
                    'required',
                    Rule::in([1, 2, 3, 4]),
                ],
                'tipoEtapa' => [
                    'required',
                    Rule::in([1, 2, 3]),
                ],
                'voceros' => ['required','array'],
                'voceros.*' => [
                    Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($request){
                        $planMedio = PlanMedio::find($request->idPlanMedio);
                        $query->where('campaign_id', $planMedio->campaign_id);
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

            $detallePlanMedioPadre = DetallePlanMedio::find($request->idDetallePlanMedioPadre);

            if($detallePlanMedioPadre->statusPublicado == 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de vincular. El estado actual del DPM base es EN PROCESO',
                ], 400);
            }

            // Validar que el DPM Padre no sea un DPM vinculado
            if($detallePlanMedioPadre->vinculado){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de vincular. El DPM base ya esta vinculado a otro DPM',
                ], 400);
            }

            // Validar que el PM del DPM Padre no sea el mismo que PM del DPM a vincular
            if($detallePlanMedioPadre->idPlanMedio == $request->idPlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de vincular en el mismo PM.',
                ], 400);
            }

            // Validar que Contacto/Programa no existe en el PM seleccionado
            /*$DPM = DetallePlanMedio::where('idPlanMedio', $request->idPlanMedio)->where('idProgramaContacto', $detallePlanMedioPadre->idProgramaContacto)->first();

            if(!is_null($DPM)){
                return response()->json([
                    'ready' => false,
                    'message' => 'Ya existe un registro con el mismo Contacto/Programa en el Plan de Medio seleccionado.',
                ], 400);
            }*/

            $planMedio = PlanMedio::find($request->idPlanMedio);

            // Datos Obligatorios
            $data = array(
                'idPlanMedio' => $request->idPlanMedio,
                'idProgramaContacto' => $detallePlanMedioPadre->idProgramaContacto,
                'idsMedioPlataforma' => $detallePlanMedioPadre->idsMedioPlataforma,
                'tipoTier' => $request->tipoTier,
                'tipoNota' => $request->tipoNota,
                'tipoEtapa' => $request->tipoEtapa,
                'statusPublicado' => $detallePlanMedioPadre->statusPublicado,
                'statusExperto' => 0,
                'vinculado' => 1,
                'idDetallePlanMedioPadre' => $detallePlanMedioPadre->id,
                'user_id' => $planMedio->user_id,
            );

            $detallePlanMedio = DetallePlanMedio::create($data);

            if (!$detallePlanMedio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha vinculado',
                ], 500);
            }

            if (isset($request->voceros)) {

                $detallePlanMedio->voceros()->sync($request->voceros);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha vinculado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function dissociateDPM($id)
    {
        //
        try {
            DB::beginTransaction();

            // Validar que el DPM sea un DPM vinculado
            $detallePlanMedio = DetallePlanMedio::find($id);
            if(!$detallePlanMedio->vinculado){
                return response()->json([
                    'ready' => false,
                    'message' => 'El DPM deseado no esta vinculado a otro DPM',
                ], 400);
            }
            
            // Eliminamos los resultados vinculados
            DetallePlanResultadoPlataforma::where('idDetallePlanMedio',$detallePlanMedio->id)->delete();

            // Clonamos las bitacoras del DPM Padre
            $detallePlanMedioPadre = DetallePlanMedio::find($detallePlanMedio->idDetallePlanMedioPadre);
            $bitacoras = Bitacora::where('idDetallePlanMedio',$detallePlanMedioPadre->id)->orderBy('id', 'asc')->get();
            foreach ($bitacoras as $bitacora) {
                # code...
                $BTC = $bitacora->replicate()->fill([
                    'idDetallePlanMedio' => $detallePlanMedio->id
                ]);

                if (!$BTC->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'El detalle de plan de medio no se ha desvinculado',
                    ], 500);
                }
            }

            // Desvinculamos
            $detallePlanMedio->vinculado = 0;
            $detallePlanMedio->idDetallePlanMedioPadre = null;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medio no se ha desvinculado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medio se ha desvinculado correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListAssociatedDPM($id)
    {
        //
        $detallePlanMedios = DetallePlanMedio::where('idDetallePlanMedioPadre', $id)->get()->map(function($detallePlanMedio){
            $detallePlanMedio->planMedio->campaign->cliente;
            $detallePlanMedio->programaContacto->programa->medio;
            $detallePlanMedio->programaContacto->contacto;
            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
            $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });
            $detallePlanMedio->voceros;
            $detallePlanMedio->bitacoras;
            $detallePlanMedio->resultados;
            return $detallePlanMedio;
        });

        return response()->json([
            'ready' => true,
            'detallePlanMedios' => $detallePlanMedios,
        ]);
    }

    public function updateMassVoceros(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idsVocero.required' => 'Voceros es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idsDetallePlanMedio' => ['required','array'],
                'idsVocero' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            foreach ($request->idsDetallePlanMedio as $idDetallePlanMedio) {

                $detallePlanMedio = DetallePlanMedio::find($idDetallePlanMedio);
                $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);

                if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign) && ($detallePlanMedio->user_id != auth()->user()->id)){
                    continue;
                }

                if(!is_null($detallePlanMedio)){
                    $detallePlanMedio->voceros()->sync($request->idsVocero);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los voceros han sido asignados correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function updateMassMuestras(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'detallePlanMedios.*.muestrasRegistradas.required' => 'La cantidad de muestras registradas es obligatorio para cada registro.',
                'detallePlanMedios.*.muestrasRegistradas.integer' => 'La cantidad de muestras registradas debe ser un numero entero.',
                'detallePlanMedios.*.muestrasEnviadas.required' => 'La cantidad de muestras enviadas es obligatorio para cada registro.',
                'detallePlanMedios.*.muestrasEnviadas.integer' => 'La cantidad de muestras enviadas debe ser un numero entero.',
                'detallePlanMedios.*.muestrasVerificadas.required' => 'La cantidad de muestras verificadas es obligatorio para cada registro.',
                'detallePlanMedios.*.muestrasVerificadas.integer' => 'La cantidad de muestras verificadas debe ser un numero entero.',
            ];

            $validator = Validator::make($request->all(), [
                'detallePlanMedios.*.idDetallePlanMedio' => ['required','distinct','exists:detalle_plan_medios,id'],
                'detallePlanMedios.*.muestrasRegistradas' => ['required','integer'],
                'detallePlanMedios.*.muestrasEnviadas' => ['required','integer'],
                'detallePlanMedios.*.muestrasVerificadas' => ['required','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            $detallePlanMedios = json_decode(json_encode($request->detallePlanMedios));

            foreach ($detallePlanMedios as $detallePlanMedio) {

                if (!(($detallePlanMedio->muestrasRegistradas >= $detallePlanMedio->muestrasEnviadas) && ($detallePlanMedio->muestrasEnviadas >= $detallePlanMedio->muestrasVerificadas))) { 
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Cantidades de muestras no validas',
                    ], 400);
                }

                $DPM = DetallePlanMedio::find($detallePlanMedio->idDetallePlanMedio);
                $planMedio = PlanMedio::find($DPM->idPlanMedio);

                if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign) && ($DPM->user_id != auth()->user()->id)){
                    continue;
                }

                $DPM->muestrasRegistradas = $detallePlanMedio->muestrasRegistradas;
                $DPM->muestrasEnviadas = $detallePlanMedio->muestrasEnviadas;
                $DPM->muestrasVerificadas = $detallePlanMedio->muestrasVerificadas;
                if (!$DPM->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Las muestras no han sido actualizadas',
                    ], 500);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Las muestras han sido actualizadas correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function updateMassTipoTier(Request $request)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'detallePlanMedios.*.tipoTier.required' => 'Tier es obligatorio para cada registro.',
                'detallePlanMedios.*.tipoTier.in' => 'Selecione Tier valido para cada registro.',
            ];

            $validator = Validator::make($request->all(), [
                'detallePlanMedios.*.idDetallePlanMedio' => ['required','distinct','exists:detalle_plan_medios,id'],
                'detallePlanMedios.*.tipoTier' => [
                    'required',
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

            $idsCampaign = auth()->user()->myCampaigns();

            $detallePlanMedios = json_decode(json_encode($request->detallePlanMedios));

            foreach ($detallePlanMedios as $detallePlanMedio) {

                $DPM = DetallePlanMedio::find($detallePlanMedio->idDetallePlanMedio);
                $planMedio = PlanMedio::find($DPM->idPlanMedio);

                if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign) && ($DPM->user_id != auth()->user()->id)){
                    continue;
                }

                $DPM->tipoTier = $detallePlanMedio->tipoTier;
                if (!$DPM->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los registros no han sido actualizados',
                    ], 500);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los registros han sido actualizados correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByContacto($idContacto)
    {
        $idsCampaign = auth()->user()->myCampaigns();

        $detallePlanMedios = DetallePlanMedio::with(
            'planMedio.campaign.cliente',
            'programaContacto.programa.medio',
            'programaContacto.contacto',
            'voceros',
            'bitacoras',
        )->whereHas('programaContacto', function (Builder $query) use ($idContacto){
            $query->where('idContacto', $idContacto);
        })->get();

        $detallePlanMedios->map(function($detallePlanMedio) use ($idsCampaign){
            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
            $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });
            if($detallePlanMedio->vinculado){
                $detallePlanMedio->detallePlanMedioPadre->bitacoras;
            }
            $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
            $detallePlanMedio->isEditable = in_array($detallePlanMedio->planMedio->campaign_id, $idsCampaign);
            return $detallePlanMedio;
        });

        return response()->json([
            'ready' => true,
            'detallePlanMedios' => $detallePlanMedios->values(),
        ]);
    }

    public function getListByContactoAndLogged($idContacto)
    {
        $idsCampaign = auth()->user()->myCampaigns();

        $detallePlanMedios = DetallePlanMedio::with(
            'planMedio.campaign.cliente',
            'programaContacto.programa.medio',
            'programaContacto.contacto',
            'voceros',
            'bitacoras',
        )->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
            $query->whereIn('campaign_id', $idsCampaign);
        })->whereHas('programaContacto', function (Builder $query) use ($idContacto){
            $query->where('idContacto', $idContacto);
        })->get();

        $detallePlanMedios->map(function($detallePlanMedio){
            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
            $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                $medioPlataforma->plataformaClasificacion->plataforma;
                return $medioPlataforma;
            });
            if($detallePlanMedio->vinculado){
                $detallePlanMedio->detallePlanMedioPadre->bitacoras;
            }
            $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
            return $detallePlanMedio;
        });

        return response()->json([
            'ready' => true,
            'detallePlanMedios' => $detallePlanMedios->values(),
        ]);
    }

    public function getListByLogged(Request $request)
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
                $detallePlanMedios = DetallePlanMedio::with(
                    'planMedio.campaign.cliente',
                    'programaContacto.programa.medio',
                    'programaContacto.contacto',
                    'voceros',
                    'bitacoras',
                )->whereDate('detalle_plan_medios.created_at', '>=', $fechaInicio)
                ->whereDate('detalle_plan_medios.created_at', '<=', $fechaFin)
                ->get();
            }else{
                $detallePlanMedios = DetallePlanMedio::with(
                    'planMedio.campaign.cliente',
                    'programaContacto.programa.medio',
                    'programaContacto.contacto',
                    'voceros',
                    'bitacoras',
                )->whereDate('detalle_plan_medios.created_at', '>=', $fechaInicio)
                ->whereDate('detalle_plan_medios.created_at', '<=', $fechaFin)
                ->where(function ($query) use ($idsCampaign){
                    $query->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
                        $query->whereIn('campaign_id', $idsCampaign);
                    })->orWhere('detalle_plan_medios.user_id', auth()->user()->id);
                })
                ->get();
            }

            /*$detallePlanMedios = DetallePlanMedio::with(
                'planMedio.campaign.cliente',
                'programaContacto.programa.medio',
                'programaContacto.contacto',
                'voceros',
                'bitacoras',
            )->whereDate('detalle_plan_medios.created_at', '>=', $fechaInicio)
            ->whereDate('detalle_plan_medios.created_at', '<=', $fechaFin)
            ->where(function ($query) use ($idsCampaign){
                $query->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
                    $query->whereIn('campaign_id', $idsCampaign);
                })->orWhere('detalle_plan_medios.user_id', auth()->user()->id);
            })
            ->get();*/

            $detallePlanMedios->map(function($detallePlanMedio) use ($idsCampaign){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                if($detallePlanMedio->vinculado){
                    $detallePlanMedio->detallePlanMedioPadre->bitacoras;
                }
                $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
                $detallePlanMedio->isEditable = in_array($detallePlanMedio->planMedio->campaign_id, $idsCampaign);
                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
                'count' => $detallePlanMedios->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function validateGetListByLoggedV2($request){
        $messages = [
        ];
    
        $validator = Validator::make($request->all(), [
            'fechaInicio' => ['required','date'],
            'fechaFin' => ['required','date'],
        ], $messages);

        return $validator;
    }

    public function getListByLoggedV2(Request $request)
    {
        $validator = $this->validateGetListByLoggedV2($request);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }
    
            $fechaInicio = $request->fechaInicio;
            $fechaFin = $request->fechaFin; 
            $estadoPublicado = $request->estadoPublicado;
            $estadoBitacora = $request->estadoBitacora;
            $clienteId = $request->clienteId;
            $campañaId = $request->campañaId;
            $planMedioId = $request->planMedioId;
            $contactoId = $request->contactoId;
            $medioId = $request->medioId;
            $programaId = $request->programaId;
    
            $detallePlan = DetallePlanMedio::with(
                'planMedio.campaign.cliente',
                'programaContacto.programa.medio',
                'programaContacto.contacto',
                'voceros',
                'bitacoras'
            )
            ->whereDate('detalle_plan_medios.created_at', '>=', $fechaInicio)
            ->whereDate('detalle_plan_medios.created_at', '<=', $fechaFin);
    
            //filtrar por clientes
            if ($clienteId !== 'null' && trim($clienteId) !== '') {
                $detallePlan = $detallePlan->whereHas('planMedio.campaign', function($query) use ($clienteId){
                    $query->where('cliente_id',$clienteId);
                });
            }else{
                $detallePlan = $detallePlan;
            } 
            //filtrar por campaña
            if ($campañaId !== 'null' && trim($campañaId) !== '') {
                $detallePlan = $detallePlan->whereHas('planMedio', function($query) use ($campañaId){
                    $query->where('campaign_id',$campañaId);
                });
            }else{
                $detallePlan = $detallePlan;
            }
    
            //filtrar por plan de medio
            if ($planMedioId !== 'null' && trim($planMedioId) !== '') {
                $detallePlan = $detallePlan->whereHas('planMedio', function($query) use ($planMedioId){
                    $query->where('id',$planMedioId);
                });
            }else{
                $detallePlan = $detallePlan;
            }
    
            //filtrar por contacto
            if ($contactoId !== 'null' && trim($contactoId) !== '') {
                $detallePlan = $detallePlan->whereHas('programaContacto', function($query) use ($contactoId){
                    $query->where('idContacto',$contactoId);
                });
            }else{
                $detallePlan = $detallePlan;
            }
    
            //filtrar por medio
            if ($medioId !== 'null' && trim($medioId) !== '') {
                $detallePlan = $detallePlan->whereHas('programaContacto.programa', function($query) use ($medioId){
                    $query->where('medio_id',$medioId);
                });
            }else{
                $detallePlan = $detallePlan;
            }
    
            //filtrar por programa
            if ($programaId !== 'null' && trim($programaId) !== '') {
                $detallePlan = $detallePlan->whereHas('programaContacto', function($query) use ($programaId){
                    $query->where('programa_id',$programaId);
                });
            }else{
                $detallePlan = $detallePlan;
            }
    
            //filtra por estado Publicado del detalle de plan de medio
            if ($estadoPublicado !== 'null' && trim($estadoPublicado) !== '') {
                $detallePlanMedios = $detallePlan->where('detalle_plan_medios.statusPublicado', $estadoPublicado);
            }else{
                $detallePlanMedios = $detallePlan;
            } 
            //filtra que solo muestre los planes de medio que tiene un agente
            $idsCampaign = auth()->user()->myCampaigns();
            if(auth()->user()->hasAnyRole(['admin','super-admin'])){
                $detallePlanMedios = $detallePlanMedios->orderBy('detalle_plan_medios.id','desc')
                ->get();
            }else{
                $detallePlanMedios= $detallePlanMedios->where(function ($query) use ($idsCampaign){
                    $query->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
                        $query->whereIn('campaign_id', $idsCampaign);
                    })->orWhere('detalle_plan_medios.user_id', auth()->user()->id);
                })
                ->orderBy('detalle_plan_medios.id','desc')
                ->get();
            }

            /* $detallePlanMedios->where(function ($query) use ($idsCampaign){
                $query->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
                    $query->whereIn('campaign_id', $idsCampaign);
                })->orWhere('detalle_plan_medios.user_id', auth()->user()->id);
            }) */
    
            $detallePlanMedios->map(function($detallePlanMedio) use ($idsCampaign){
                $detallePlanMedio->bitacoras = Bitacora::where('bitacoras.idDetallePlanMedio','=',$detallePlanMedio->id)
                                                        ->orderBy('bitacoras.id','desc')->first();
                if($detallePlanMedio->vinculado){
                    $detallePlanMedio->detallePlanMedioPadre->bitacoras;
                }
                $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
                $detallePlanMedio->isEditable = in_array($detallePlanMedio->planMedio->campaign_id, $idsCampaign);
                return $detallePlanMedio;
            });
    
            // filtra por estado de la ultima bitacora creada 
            if ($estadoBitacora !== 'null' && trim($estadoBitacora) !== '') {
                $detalle_plan_medio = $detallePlanMedios->where('bitacoras.estado', $estadoBitacora);
                $detalle_plan_medio = $detalle_plan_medio->values();
            }else{
                $detalle_plan_medio = $detallePlanMedios;
            }
            
            //pagina al resultado
            $detalle_plan_medio = $this->paginateCollection($detalle_plan_medio, 10, $pageName = 'page', $fragment = null);
            
    
            return new DetallePlanMedioCollection($detalle_plan_medio);        
        try {
            
            
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function paginateCollection($collection, $perPage, $pageName = 'page', $fragment = null)
    {
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage($pageName);
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage);
        parse_str(request()->getQueryString(), $query);
        unset($query[$pageName]);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'pageName' => $pageName,
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $query,
                'fragment' => $fragment
            ]
        );

        return $paginator;
    }

    public function getListByEstado($estado)
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->where('detalle_plan_medios.statusPublicado', $estado)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByEstados(Request $request)
    {

        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'estados' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', $request->estados)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByEstadoENC($estado)
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->where('detalle_plan_medios.statusPublicado', $estado)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $contacto = $detallePlanMedio->programaContacto->contacto;
                $detallePlanMedio->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO

                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByEstadosENC(Request $request)
    {

        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'estados' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', $request->estados)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $contacto = $detallePlanMedio->programaContacto->contacto;
                $detallePlanMedio->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO

                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getCountByEstado()
    {

        $data = DetallePlanMedio::selectRaw("detalle_plan_medios.statusPublicado as Estado, COUNT(1) as cantidad")
        ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
        ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
        ->groupBy("Estado")
        ->orderByRaw("Estado ASC")
        ->get();
    
        return $data;
    }

    public function getCountByEstadoAndByLogged()
    {
        $idsCampaign = auth()->user()->myCampaigns();

        $data = DetallePlanMedio::selectRaw("detalle_plan_medios.statusPublicado as Estado, YEAR(detalle_plan_medios.created_at) as year, MONTH(detalle_plan_medios.created_at) AS month, COUNT(1) as cantidad")
        ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
        ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
        ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
        ->whereIn('campaigns.id', $idsCampaign)
        ->whereNull('plan_medios.deleted_at')
        ->whereNull('campaigns.deleted_at')
        ->whereNull('clientes.deleted_at')
        ->groupBy("Estado", "year", "month")
        ->orderByRaw("Estado ASC, year ASC, month ASC")
        ->get();

        return response()->json([
            'ready' => true,
            'data' => $data
        ]);
    }

    public function getListByEstadoAndByLogged(Request $request)
    {
        $messages = [
        ];

        $validator = Validator::make($request->all(), [
            'estado' => ['required'],
            'year' => ['required','date_format:Y'],
            'month' => ['required','date_format:m'],
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $idsCampaign = auth()->user()->myCampaigns();

        $detallePlanMedios = DetallePlanMedio::with(
            'planMedio.campaign.cliente',
            'programaContacto.programa.medio',
            'programaContacto.contacto',
            'voceros',
            'bitacoras',
        )->whereHas('planMedio', function (Builder $query) use ($idsCampaign){
            $query->whereIn('campaign_id', $idsCampaign);
        })->where('detalle_plan_medios.statusPublicado', $request->estado)
        ->whereRaw('YEAR(detalle_plan_medios.created_at) = ?', [$request->year])
        ->whereRaw('MONTH(detalle_plan_medios.created_at) = ?', [$request->month])
        ->get();


        $detallePlanMedios->map(function($detallePlanMedio){
            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

            $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
            ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
            ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
            ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
            ->get();

            if($detallePlanMedio->vinculado){
                $detallePlanMedio->detallePlanMedioPadre->bitacoras;
            }
            $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
            return $detallePlanMedio;
        });

        return response()->json([
            'ready' => true,
            'detallePlanMedios' => $detallePlanMedios->values(),
            'count' => $detallePlanMedios->count(),
        ]);
    }

    public function transferir(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'idDetallePlanMedio.required' => 'El Detalle de Plan de Medios es obligatorio.',
                'idDetallePlanMedio.exists' => 'Seleccione un Detalle de Plan de Medios valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idDetallePlanMedio' => ['required','exists:detalle_plan_medios,id'],
                'user_id' => ['required','exists:users,id'],
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

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para transferir el DPM',
                ], 400);
            }

            $statusMessage = array(
                0 => 'EN PROCESO',
                1 => 'PUBLICADO',
                2 => 'RECHAZADO',
                3 => 'CANCELADO',
            );

            if($detallePlanMedio->statusPublicado != 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de transferir el registro. Su estado actual es '.$statusMessage[$detallePlanMedio->statusPublicado],
                ], 400);
            }

            if($detallePlanMedio->user_id == $request->user_id){
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medios ya se encuentra asignado al agente deseado',
                ], 400);
            }

            $detallePlanMedio->user_id = $request->user_id;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El detalle de plan de medios no se ha transferido',
                ], 500);
            }

            // Creacion de bitacora de transferencia
            $lastBitacora = Bitacora::where('idDetallePlanMedio', $detallePlanMedio->id)->orderBy('created_at', 'desc')->first();

            $dataBitacora = array(
                'idDetallePlanMedio' => $detallePlanMedio->id,
                'tipoBitacora' => 2,
                'estado' => $lastBitacora->estado,
                'observacion' => 'Transferencia',
                'idTipoComunicacion' => null,
                'user_id' => auth()->user()->id,
                'idUserExtra' => $request->user_id,
            );

            $bitacora = Bitacora::create($dataBitacora);

            if (!$bitacora->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear una bitacora',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El detalle de plan de medios se ha transferido correctamente',
                'detallePlanMedio' => $detallePlanMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function transferirMass(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'idsDetallePlanMedio' => ['required','array'],
                'idsDetallePlanMedio.*' => ['exists:detalle_plan_medios,id'],
                'user_id' => ['required','exists:users,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin'])){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para transferir DPMs',
                ], 400);
            }*/

            $idsCampaign = auth()->user()->myCampaigns();

            foreach ($request->idsDetallePlanMedio as $idDetallePlanMedio) {

                $detallePlanMedio = DetallePlanMedio::find($idDetallePlanMedio);
                $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);

                if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                    continue;
                }

                if($detallePlanMedio->statusPublicado != 0){
                    continue;
                }

                if($detallePlanMedio->user_id == $request->user_id){
                    continue;
                }

                $detallePlanMedio->user_id = $request->user_id;
                if (!$detallePlanMedio->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los DPMs no se han transferido',
                    ], 500);
                }

                // Creacion de bitacora de transferencia
                $lastBitacora = Bitacora::where('idDetallePlanMedio', $detallePlanMedio->id)->orderBy('created_at', 'desc')->first();

                $dataBitacora = array(
                    'idDetallePlanMedio' => $detallePlanMedio->id,
                    'tipoBitacora' => 2,
                    'estado' => $lastBitacora->estado,
                    'observacion' => 'Transferencia',
                    'idTipoComunicacion' => null,
                    'user_id' => auth()->user()->id,
                    'idUserExtra' => $request->user_id,
                );

                $bitacora = Bitacora::create($dataBitacora);

                if (!$bitacora->id) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Error al intentar crear una bitacora',
                    ], 500);
                }

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los DPMs se han transferido correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    /** SISTEMA EXPERTO */

    public function getListSistemaExpertoEnviados()
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', [1,3])
            ->where('detalle_plan_medios.statusExperto', 1)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);
        
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListSistemaExpertoNoEnviados()
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', [1,3])
            ->where('detalle_plan_medios.statusExperto', 0)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListSistemaExpertoEnviadosENC()
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', [1,3])
            ->where('detalle_plan_medios.statusExperto', 1)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $contacto = $detallePlanMedio->programaContacto->contacto;
                $detallePlanMedio->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO

                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);
        
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListSistemaExpertoNoEnviadosENC()
    {

        try {

            set_time_limit(300);

            $detallePlanMedios = DetallePlanMedio::selectRaw("DISTINCT detalle_plan_medios.*, clientes.nombreComercial as Cliente, campaigns.id as idCampaign, 
            campaigns.titulo as Campaign, campaigns.tipoPublico, campaigns.tipoObjetivo, campaigns.tipoAudiencia, campaigns.interesPublico, campaigns.novedad,
            campaigns.actualidad, campaigns.autoridadCliente, campaigns.mediaticoCliente, campaigns.autoridadVoceros, campaigns.mediaticoVoceros,
            plan_medios.nombre as PlanMedio, CONCAT(personas.nombres,' ',personas.apellidos) as Contacto, medios.nombre as Medio, programas.nombre as Programa")
            ->join("programa_contactos", "programa_contactos.id", "=", "detalle_plan_medios.idProgramaContacto")
            ->join("personas", "personas.id", "=", "programa_contactos.idContacto")
            ->join("programas", "programas.id", "=", "programa_contactos.programa_id")
            ->join("medios", "medios.id", "=", "programas.medio_id")
            ->join("plan_medios", "plan_medios.id", "=", "detalle_plan_medios.idPlanMedio")
            ->join("campaigns", "campaigns.id", "=", "plan_medios.campaign_id")
            ->join("clientes", "clientes.id", "=", "campaigns.cliente_id")
            ->whereNull('plan_medios.deleted_at')
            ->whereNull('campaigns.deleted_at')
            ->whereNull('clientes.deleted_at')
            ->whereIn('detalle_plan_medios.statusPublicado', [1,3])
            ->where('detalle_plan_medios.statusExperto', 0)
            ->orderBy('detalle_plan_medios.id', 'desc')
            ->get();
            
            $detallePlanMedios->map(function($detallePlanMedio){
                $contacto = $detallePlanMedio->programaContacto->contacto;
                $detallePlanMedio->Contacto = $contacto->nombres . " " . $contacto->apellidos; // NOMBRE ENCRIPTADO

                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);

                $detallePlanMedio->medioPlataformas = MedioPlataforma::selectRaw("DISTINCT medio_plataformas.*, plataforma_clasificacions.descripcion as Clasificacion, plataformas.descripcion as Plataforma")
                ->join("plataforma_clasificacions", "plataforma_clasificacions.id", "=", "medio_plataformas.idPlataformaClasificacion")
                ->join("plataformas", "plataformas.id", "=", "plataforma_clasificacions.plataforma_id")
                ->whereIn('medio_plataformas.id', $idsMedioPlataforma)
                ->get();

                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    private function campaignHaveValores($campaign)
    {
        return !empty($campaign->tipoPublico) && !empty($campaign->tipoObjetivo) && !empty($campaign->tipoAudiencia) && !empty($campaign->interesPublico) && !empty($campaign->novedad) && 
        !empty($campaign->actualidad) && !empty($campaign->autoridadCliente) && !empty($campaign->mediaticoCliente) && !empty($campaign->autoridadVoceros) && !empty($campaign->mediaticoVoceros);
    }

    private function campaignHavePesos($campaign)
    {
        return !empty($campaign->pesoPublico) && !empty($campaign->pesoObjetivo) && !empty($campaign->pesoAudiencia) && !empty($campaign->pesoInteresPublico) && !empty($campaign->pesoNovedad) && 
        !empty($campaign->pesoActualidad) && !empty($campaign->pesoAutoridadCliente) && !empty($campaign->pesoMediaticoCliente) && !empty($campaign->pesoAutoridadVoceros) && !empty($campaign->pesoMediaticoVoceros);
    }

    public function generatePlanMedio(Request $request)
    {
      //
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'K' => ['required','integer'],
                'considerarCancelados' => ['required','boolean'],
                'idsPlataforma' => ['present','array'],
                'idsPlataforma.*' => ['exists:plataformas,id'],
                'meses' => ['present','array'],
                'meses.*' => ['integer','min:1','max:12'],
                'duracionMin' => ['required','integer'],
                'duracionMax' => ['required','integer'],
                'excluir' => ['required','boolean'],
                'idsEtiqueta' => ['present','array'],
                'idsEtiqueta.*' => ['exists:etiquetas,id'],
                'idCampaign' => ['required','exists:campaigns,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsPlataforma = implode(',', $request->idsPlataforma);
            $meses = implode(',', $request->meses);
            $etiquetasCampana = Etiqueta::findMany($request->idsEtiqueta)->map(function($etiqueta){
                return $etiqueta->slug;
            })->implode(',');
            $campaign = Campaign::find($request->idCampaign);

            if (!$this->campaignHaveValores($campaign) || !$this->campaignHavePesos($campaign)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña seleccionada no cuenta con valorizacion o no esta completa. Por favor de completarla.',
                ], 400);
            }

            $data = array(
                'K' => $request->K,
                'considerarCancelados' => $request->considerarCancelados,
                'idsPlataforma' => $idsPlataforma,
                'meses' => $meses,
                'duracionMin' => $request->duracionMin,
                'duracionMax' => $request->duracionMax,
                'excluir' => $request->excluir,
                'etiquetasCampana' => $etiquetasCampana,
                'objetivo' => $campaign->tipoObjetivo,
                'publicoObjetivo' => $campaign->tipoPublico,
                'edad' => $campaign->tipoAudiencia,
                'interesPublico' => $campaign->interesPublico,
                'novedad' => $campaign->novedad,
                'actualidad' => $campaign->actualidad,
                'mediaticoCliente' => $campaign->mediaticoCliente,
                'autoridadCliente' => $campaign->autoridadCliente,
                'mediaticoVoceros' => $campaign->mediaticoVoceros,
                'autoridadVoceros' => $campaign->autoridadVoceros,
                'pesoObjetivo' => $campaign->pesoObjetivo,
                'pesoPublicoObjetivo' => $campaign->pesoPublico,
                'pesoEdad' => $campaign->pesoAudiencia,
                'pesoInteresPublico' => $campaign->pesoInteresPublico,
                'pesoNovedad' => $campaign->pesoNovedad,
                'pesoActualidad' => $campaign->pesoActualidad,
                'pesoMediaticoCliente' => $campaign->pesoMediaticoCliente,
                'pesoAutoridadCliente' => $campaign->pesoAutoridadCliente,
                'pesoMediaticoVoceros' => $campaign->pesoMediaticoVoceros,
                'pesoAutoridadVoceros' => $campaign->pesoAutoridadVoceros,
            );

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            // Consultamos al SE
            $response = Http::get('http://18.218.39.117:8000/casos/consultar', [
                'securitytoken' => 123456,
                'data' => json_encode($data),
            ]);

            $idsDetallePlanMedios = $response->json()['idsDetallePlanMedios'];

            if (empty($idsDetallePlanMedios)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'No se encontraron contactos con las características ingresadas. Ingrese nuevamente.',
                ], 400);
            }    

            $idsDPM = explode(',', $idsDetallePlanMedios);

            $detallePlanMedios = DetallePlanMedio::findMany($idsDPM)->map(function($detallePlanMedio){
                $detallePlanMedio->programaContacto->programa->medio;
                $detallePlanMedio->programaContacto->contacto;
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
                $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                    $medioPlataforma->plataformaClasificacion->plataforma;
                    return $medioPlataforma;
                });
                return $detallePlanMedio;
            });

            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function generatePlanMedioV2(Request $request)
    {
      //
        try {
            $messages = [
            ];
        
            $validator = Validator::make($request->all(), [
                'K' => ['required','integer'],
                'considerarCancelados' => ['required','boolean'],
                'idsPlataforma' => ['present','array'],
                'idsPlataforma.*' => ['exists:plataformas,id'],
                'meses' => ['present','array'],
                'meses.*' => ['integer','min:1','max:12'],
                'duracionMin' => ['required','integer'],
                'duracionMax' => ['required','integer'],
                'excluir' => ['required','boolean'],
                'idsEtiqueta' => ['present','array'],
                'idsEtiqueta.*' => ['exists:etiquetas,id'],
                'idCampaign' => ['required','exists:campaigns,id'],
                'pesoObjetivo' => ['required','integer'],
                'pesoPublicoObjetivo' => ['required','integer'],
                'pesoEdad' => ['required','integer'],
                'pesoInteresPublico' => ['required','integer'],
                'pesoNovedad' => ['required','integer'],
                'pesoActualidad' => ['required','integer'],
                'pesoAutoridadCliente' => ['required','integer'],
                'pesoMediaticoCliente' => ['required','integer'],
                'pesoAutoridadVoceros' => ['required','integer'],
                'pesoMediaticoVoceros' => ['required','integer'],
            ], $messages);
        
            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }
        
            $idsPlataforma = implode(',', $request->idsPlataforma);
            $meses = implode(',', $request->meses);
            $etiquetasCampana = Etiqueta::findMany($request->idsEtiqueta)->map(function($etiqueta){
                return $etiqueta->slug;
            })->implode(',');
            $campaign = Campaign::find($request->idCampaign);
        
            if (!$this->campaignHaveValores($campaign)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña seleccionada no cuenta con valorizacion. Por favor de completarla.',
                ], 400);
            }
        
            $data = array(
                'K' => $request->K,
                'considerarCancelados' => $request->considerarCancelados,
                'idsPlataforma' => $idsPlataforma,
                'meses' => $meses,
                'duracionMin' => $request->duracionMin,
                'duracionMax' => $request->duracionMax,
                'excluir' => $request->excluir,
                'etiquetasCampana' => $etiquetasCampana,
                'objetivo' => $campaign->tipoObjetivo,
                'publicoObjetivo' => $campaign->tipoPublico,
                'edad' => $campaign->tipoAudiencia,
                'interesPublico' => $campaign->interesPublico,
                'novedad' => $campaign->novedad,
                'actualidad' => $campaign->actualidad,
                'mediaticoCliente' => $campaign->mediaticoCliente,
                'autoridadCliente' => $campaign->autoridadCliente,
                'mediaticoVoceros' => $campaign->mediaticoVoceros,
                'autoridadVoceros' => $campaign->autoridadVoceros,
                'pesoObjetivo' => $request->pesoObjetivo,
                'pesoPublicoObjetivo' => $request->pesoPublicoObjetivo,
                'pesoEdad' => $request->pesoEdad,
                'pesoInteresPublico' => $request->pesoInteresPublico,
                'pesoNovedad' => $request->pesoNovedad,
                'pesoActualidad' => $request->pesoActualidad,
                'pesoMediaticoCliente' => $request->pesoMediaticoCliente,
                'pesoAutoridadCliente' => $request->pesoAutoridadCliente,
                'pesoMediaticoVoceros' => $request->pesoMediaticoVoceros,
                'pesoAutoridadVoceros' => $request->pesoAutoridadVoceros,
            );
        
            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');
        
            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }
        
            // Consultamos al SE
            $response = Http::get('http://18.218.39.117:8000/casos/consultar', [
                'securitytoken' => 123456,
                'data' => json_encode($data),
            ]);
        
            $idsDetallePlanMedios = $response->json()['idsDetallePlanMedios'];
        
            if (empty($idsDetallePlanMedios)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'No se encontraron contactos con las características ingresadas. Ingrese nuevamente.',
                ], 400);
            }    
        
            $idsDPM = explode(',', $idsDetallePlanMedios);
        
            //return $idsDPM;
        
        
            $detallePlanMedios = DetallePlanMedio::findMany($idsDPM)->map(function($detallePlanMedio){
                $detallePlanMedio->programaContacto->programa->medio;
                $detallePlanMedio->programaContacto->contacto;
        
                //recuperamos idPlataforma de los contactos
                $idsMedioPlataformaContactos = explode(',',  $detallePlanMedio->programaContacto->idsMedioPlataforma);
                //recuperamos idPlataforma de los programas
                $idProgramaPlataformas = ProgramaPlataforma::where('programa_id',$detallePlanMedio->programaContacto->programa_id)
                ->get()->map(function($programaPlataforma){
                    return $programaPlataforma['idMedioPlataforma'];
                });

                //eliminamos idplataforma de contactos que no coinciden con los progrmas
                $detallePlanMedio->programaContacto->idsMedioPlataforma = array_intersect($idsMedioPlataformaContactos,$idProgramaPlataformas->toArray());
        
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
                $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                    $medioPlataforma->plataformaClasificacion->plataforma;
                    return $medioPlataforma;
                });
                return $detallePlanMedio;
            });
            return response()->json([
                'ready' => true,
                'detallePlanMedios' => $detallePlanMedios,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function analisisEstrategico(Request $request)
    {
      //
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'idsEtiqueta' => ['present','array'],
                'idsEtiqueta.*' => ['exists:etiquetas,id'],
                'objetivo' => ['required','integer'],
                'publicoObjetivo' => ['required','integer'],
                'edad' => ['required','integer'],
                'interesPublico' => ['required','integer'],
                'novedad' => ['required','integer'],
                'actualidad' => ['required','integer'],
                'autoridadCliente' => ['required','integer'],
                'mediaticoCliente' => ['required','integer'],
                'autoridadVoceros' => ['required','integer'],
                'mediaticoVoceros' => ['required','integer'],
                'pesoObjetivo' => ['required','integer'],
                'pesoPublicoObjetivo' => ['required','integer'],
                'pesoEdad' => ['required','integer'],
                'pesoInteresPublico' => ['required','integer'],
                'pesoNovedad' => ['required','integer'],
                'pesoActualidad' => ['required','integer'],
                'pesoAutoridadCliente' => ['required','integer'],
                'pesoMediaticoCliente' => ['required','integer'],
                'pesoAutoridadVoceros' => ['required','integer'],
                'pesoMediaticoVoceros' => ['required','integer'],
                'proximidad' => ['required','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $etiquetasCampana = Etiqueta::findMany($request->idsEtiqueta)->map(function($etiqueta){
                return $etiqueta->slug;
            })->implode(',');

            $data = array(
                'etiquetasCampana' => $etiquetasCampana,
                'objetivo' => $request->objetivo,
                'publicoObjetivo' => $request->publicoObjetivo,
                'edad' => $request->edad,
                'interesPublico' => $request->interesPublico,
                'novedad' => $request->novedad,
                'actualidad' => $request->actualidad,
                'mediaticoCliente' => $request->mediaticoCliente,
                'autoridadCliente' => $request->autoridadCliente,
                'mediaticoVoceros' => $request->mediaticoVoceros,
                'autoridadVoceros' => $request->autoridadVoceros,
                'pesoObjetivo' => $request->pesoObjetivo,
                'pesoPublicoObjetivo' => $request->pesoPublicoObjetivo,
                'pesoEdad' => $request->pesoEdad,
                'pesoInteresPublico' => $request->pesoInteresPublico,
                'pesoNovedad' => $request->pesoNovedad,
                'pesoActualidad' => $request->pesoActualidad,
                'pesoMediaticoCliente' => $request->pesoMediaticoCliente,
                'pesoAutoridadCliente' => $request->pesoAutoridadCliente,
                'pesoMediaticoVoceros' => $request->pesoMediaticoVoceros,
                'pesoAutoridadVoceros' => $request->pesoAutoridadVoceros,
                'proximidad' => $request->proximidad,
            );

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            // Consultamos al SE
            $response = Http::get('http://18.218.39.117:8000/casos/estadisticas', [
                'securitytoken' => 123456,
                'data' => json_encode($data),
            ]);

            return response()->json([
                'ready' => true,
                'data' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function addSistemaExperto($id)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'id.exists' => 'El registro deseado no existe.',
            ];

            $params = array(
                'id' => $id,
            );

            $validator = Validator::make($params, [
                'id' => ['exists:detalle_plan_medios,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $detallePlanMedio = DetallePlanMedio::find($id);

            if ($detallePlanMedio->statusPublicado != 1) {
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro deseado no esta PUBLICADO.',
                ], 400);
            }   

            if ($detallePlanMedio->statusExperto == 1) {
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro deseado ya ha sido enviado al Sistema Experto.',
                ], 400);
            }   
            
            $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
            $campaign = Campaign::find($planMedio->campaign_id);

            if (!$this->campaignHaveValores($campaign)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña del registro deseado no cuenta con valorizacion o no esta completa. Por favor de completarla.',
                ], 400);
            }

            $countEtiquetas = $campaign->etiquetas()->count();

            if($countEtiquetas == 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no cuenta con etiquetas. Por favor agregue al menos una.',
                ], 400);
            }

            $etiquetasCampana = $campaign->etiquetas->map(function($etiqueta){
                return $etiqueta->slug;
            })->implode(',');

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $casos = array();

            $detallePlanResultadoPlataformas = DetallePlanResultadoPlataforma::where("idDetallePlanMedio", $detallePlanMedio->id)->get();

            foreach ($detallePlanResultadoPlataformas as $detallePlanResultadoPlataforma) {
                # code...
                $resultadoPlataforma = ResultadoPlataforma::find($detallePlanResultadoPlataforma->idResultadoPlataforma);
                $medioPlataforma = MedioPlataforma::with('plataformaClasificacion')->find($resultadoPlataforma->idMedioPlataforma);

                $fechaInicio= strtotime($campaign->fechaInicio);
                $fechaPublicacion = strtotime($resultadoPlataforma->fechaPublicacion);
                $numDias = floor(($fechaPublicacion - $fechaInicio) / 86400);

                $data = array(
                    'idDetallePlanMedios' => $detallePlanMedio->id,
                    'idTipoNota' => $detallePlanMedio->tipoNota,
                    'idContactoMedio' => $detallePlanMedio->idProgramaContacto,
                    'idPlataforma' => $medioPlataforma->plataformaClasificacion->plataforma_id,
                    'idClasificacion' => $medioPlataforma->idPlataformaClasificacion,
                    'numDias' => $numDias,
                    'fechaPublicacion' => $fechaPublicacion,
                    'idCliente' => $campaign->cliente_id,
                    'idCampana' => $campaign->id,
                    'etiquetasCampana' => $etiquetasCampana,
                    'objetivo' => $campaign->tipoObjetivo,
                    'publicoObjetivo' => $campaign->tipoPublico,
                    'edad' => $campaign->tipoAudiencia,
                    'interesPublico' => $campaign->interesPublico,
                    'novedad' => $campaign->novedad,
                    'actualidad' => $campaign->actualidad,
                    'mediaticoCliente' => $campaign->mediaticoCliente,
                    'autoridadCliente' => $campaign->autoridadCliente,
                    'mediaticoVoceros' => $campaign->mediaticoVoceros,
                    'autoridadVoceros' => $campaign->autoridadVoceros,
                );

                // Agregamos el registro al SE
                $response = Http::asForm()->post('http://18.218.39.117:8000/casos/agregar', [
                    'securitytoken' => 123456,
                    'data' => json_encode($data),
                ]);

                array_push($casos, $data);

            }

            // Actualizamos el estado SE del registro
            $detallePlanMedio->statusExperto = 1;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar actualizar el estado SE del registro',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los casos se han enviado correctamente al Sistema Experto.',
                'casos' => $casos,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function addSistemaExpertoV2($id)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'id.exists' => 'El registro deseado no existe.',
            ];

            $params = array(
                'id' => $id,
            );

            $validator = Validator::make($params, [
                'id' => ['exists:detalle_plan_medios,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $detallePlanMedio = DetallePlanMedio::find($id);

            if ($detallePlanMedio->statusPublicado != 1 && $detallePlanMedio->statusPublicado != 3) {
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro deseado no esta PUBLICADO o CANCELADO.',
                ], 400);
            }   

            if ($detallePlanMedio->statusExperto == 1) {
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro deseado ya ha sido enviado al Sistema Experto.',
                ], 400);
            }   
            
            $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
            $campaign = Campaign::find($planMedio->campaign_id);

            if (!$this->campaignHaveValores($campaign)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña del registro deseado no cuenta con valorizacion. Por favor de completarla.',
                ], 400);
            }

            $countEtiquetas = $campaign->etiquetas()->count();

            if($countEtiquetas == 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'La campaña no cuenta con etiquetas. Por favor agregue al menos una.',
                ], 400);
            }

            $etiquetasCampana = $campaign->etiquetas->map(function($etiqueta){
                return $etiqueta->slug;
            })->implode(',');

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $casos = array();

            $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
            $medioPlataformas = MedioPlataforma::with('plataformaClasificacion.plataforma')->whereIn('id', $idsMedioPlataforma)->get();

            foreach ($medioPlataformas as $medioPlataforma) {
                # code...
                $resultadoPlataforma = $detallePlanMedio->resultados()->where("idMedioPlataforma", $medioPlataforma->id)->first();

                $fechaInicio= strtotime($campaign->fechaInicio);
                $fechaPublicacion = !is_null($resultadoPlataforma) ? strtotime($resultadoPlataforma->fechaPublicacion) : strtotime($detallePlanMedio->updated_at);
                $numDias = floor(($fechaPublicacion - $fechaInicio) / 86400);

                $data = array(
                    'idDetallePlanMedios' => $detallePlanMedio->id,
                    'statusPublicado' => $detallePlanMedio->statusPublicado,
                    'idTipoNota' => $detallePlanMedio->tipoNota,
                    'idContactoMedio' => $detallePlanMedio->idProgramaContacto,
                    'idPlataforma' => $medioPlataforma->plataformaClasificacion->plataforma_id,
                    'idClasificacion' => $medioPlataforma->idPlataformaClasificacion,
                    'numDias' => $numDias,
                    'fechaPublicacion' => $fechaPublicacion,
                    'idCliente' => $campaign->cliente_id,
                    'idCampana' => $campaign->id,
                    'etiquetasCampana' => $etiquetasCampana,
                    'objetivo' => $campaign->tipoObjetivo,
                    'publicoObjetivo' => $campaign->tipoPublico,
                    'edad' => $campaign->tipoAudiencia,
                    'interesPublico' => $campaign->interesPublico,
                    'novedad' => $campaign->novedad,
                    'actualidad' => $campaign->actualidad,
                    'mediaticoCliente' => $campaign->mediaticoCliente,
                    'autoridadCliente' => $campaign->autoridadCliente,
                    'mediaticoVoceros' => $campaign->mediaticoVoceros,
                    'autoridadVoceros' => $campaign->autoridadVoceros,
                );

                // Agregamos el registro al SE
                $response = Http::asForm()->post('http://18.218.39.117:8000/casos/agregar', [
                    'securitytoken' => 123456,
                    'data' => json_encode($data),
                ]);

                array_push($casos, $data);

            }

            // Actualizamos el estado SE del registro
            $detallePlanMedio->statusExperto = 1;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar actualizar el estado SE del registro',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los casos se han enviado correctamente al Sistema Experto.',
                'casos' => $casos,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function addMassSistemaExperto(Request $request)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'idsDetallePlanMedio.required' => 'DPMs es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idsDetallePlanMedio' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $detallePlanMedioEnviados = array();
            $detallePlanMedioNoEnviados = array();

            foreach ($request->idsDetallePlanMedio as $idDetallePlanMedio) {

                $detallePlanMedio = DetallePlanMedio::find($idDetallePlanMedio);

                if ($detallePlanMedio->statusPublicado != 1) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }   
    
                if ($detallePlanMedio->statusExperto == 1) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }   
                
                $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
                $campaign = Campaign::find($planMedio->campaign_id);
    
                if (!$this->campaignHaveValores($campaign)) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }
    
                $countEtiquetas = $campaign->etiquetas()->count();
    
                if($countEtiquetas == 0){
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }
    
                $etiquetasCampana = $campaign->etiquetas->map(function($etiqueta){
                    return $etiqueta->slug;
                })->implode(',');
    
                $detallePlanResultadoPlataformas = DetallePlanResultadoPlataforma::where("idDetallePlanMedio", $detallePlanMedio->id)->get();
    
                foreach ($detallePlanResultadoPlataformas as $detallePlanResultadoPlataforma) {
                    # code...
                    $resultadoPlataforma = ResultadoPlataforma::find($detallePlanResultadoPlataforma->idResultadoPlataforma);
                    $medioPlataforma = MedioPlataforma::with('plataformaClasificacion')->find($resultadoPlataforma->idMedioPlataforma);
    
                    $fechaInicio= strtotime($campaign->fechaInicio);
                    $fechaPublicacion = strtotime($resultadoPlataforma->fechaPublicacion);
                    $numDias = floor(($fechaPublicacion - $fechaInicio) / 86400);
    
                    $data = array(
                        'idDetallePlanMedios' => $detallePlanMedio->id,
                        'idTipoNota' => $detallePlanMedio->tipoNota,
                        'idContactoMedio' => $detallePlanMedio->idProgramaContacto,
                        'idPlataforma' => $medioPlataforma->plataformaClasificacion->plataforma_id,
                        'idClasificacion' => $medioPlataforma->idPlataformaClasificacion,
                        'numDias' => $numDias,
                        'fechaPublicacion' => $fechaPublicacion,
                        'idCliente' => $campaign->cliente_id,
                        'idCampana' => $campaign->id,
                        'etiquetasCampana' => $etiquetasCampana,
                        'objetivo' => $campaign->tipoObjetivo,
                        'publicoObjetivo' => $campaign->tipoPublico,
                        'edad' => $campaign->tipoAudiencia,
                        'interesPublico' => $campaign->interesPublico,
                        'novedad' => $campaign->novedad,
                        'actualidad' => $campaign->actualidad,
                        'mediaticoCliente' => $campaign->mediaticoCliente,
                        'autoridadCliente' => $campaign->autoridadCliente,
                        'mediaticoVoceros' => $campaign->mediaticoVoceros,
                        'autoridadVoceros' => $campaign->autoridadVoceros,
                    );
    
                    // Agregamos el registro al SE
                    $response = Http::asForm()->post('http://18.218.39.117:8000/casos/agregar', [
                        'securitytoken' => 123456,
                        'data' => json_encode($data),
                    ]);
    
                }
    
                // Actualizamos el estado SE del registro
                $detallePlanMedio->statusExperto = 1;
                if (!$detallePlanMedio->save()) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }

                array_push($detallePlanMedioEnviados, $detallePlanMedio);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Se han enviado '.count($detallePlanMedioEnviados).' caso(s) de '.count($request->idsDetallePlanMedio).' solicitado(s) al Sistema Experto.',
                'detallePlanMedioEnviados' => $detallePlanMedioEnviados,
                'detallePlanMedioNoEnviados' => $detallePlanMedioNoEnviados,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function addMassSistemaExpertoV2(Request $request)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'idsDetallePlanMedio.required' => 'DPMs es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idsDetallePlanMedio' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $detallePlanMedioEnviados = array();
            $detallePlanMedioNoEnviados = array();

            foreach ($request->idsDetallePlanMedio as $idDetallePlanMedio) {

                $detallePlanMedio = DetallePlanMedio::find($idDetallePlanMedio);

                if ($detallePlanMedio->statusPublicado != 1 && $detallePlanMedio->statusPublicado != 3) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }   
    
                if ($detallePlanMedio->statusExperto == 1) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }   
                
                $planMedio = PlanMedio::find($detallePlanMedio->idPlanMedio);
                $campaign = Campaign::find($planMedio->campaign_id);
    
                if (!$this->campaignHaveValores($campaign)) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }
    
                $countEtiquetas = $campaign->etiquetas()->count();
    
                if($countEtiquetas == 0){
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }
    
                $etiquetasCampana = $campaign->etiquetas->map(function($etiqueta){
                    return $etiqueta->slug;
                })->implode(',');
    
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
                $medioPlataformas = MedioPlataforma::with('plataformaClasificacion.plataforma')->whereIn('id', $idsMedioPlataforma)->get();
    
                foreach ($medioPlataformas as $medioPlataforma) {
                    # code...
                    $resultadoPlataforma = $detallePlanMedio->resultados()->where("idMedioPlataforma", $medioPlataforma->id)->first();

                    $fechaInicio= strtotime($campaign->fechaInicio);
                    $fechaPublicacion = !is_null($resultadoPlataforma) ? strtotime($resultadoPlataforma->fechaPublicacion) : strtotime($detallePlanMedio->updated_at);
                    $numDias = floor(($fechaPublicacion - $fechaInicio) / 86400);
    
                    $data = array(
                        'idDetallePlanMedios' => $detallePlanMedio->id,
                        'statusPublicado' => $detallePlanMedio->statusPublicado,
                        'idTipoNota' => $detallePlanMedio->tipoNota,
                        'idContactoMedio' => $detallePlanMedio->idProgramaContacto,
                        'idPlataforma' => $medioPlataforma->plataformaClasificacion->plataforma_id,
                        'idClasificacion' => $medioPlataforma->idPlataformaClasificacion,
                        'numDias' => $numDias,
                        'fechaPublicacion' => $fechaPublicacion,
                        'idCliente' => $campaign->cliente_id,
                        'idCampana' => $campaign->id,
                        'etiquetasCampana' => $etiquetasCampana,
                        'objetivo' => $campaign->tipoObjetivo,
                        'publicoObjetivo' => $campaign->tipoPublico,
                        'edad' => $campaign->tipoAudiencia,
                        'interesPublico' => $campaign->interesPublico,
                        'novedad' => $campaign->novedad,
                        'actualidad' => $campaign->actualidad,
                        'mediaticoCliente' => $campaign->mediaticoCliente,
                        'autoridadCliente' => $campaign->autoridadCliente,
                        'mediaticoVoceros' => $campaign->mediaticoVoceros,
                        'autoridadVoceros' => $campaign->autoridadVoceros,
                    );
    
                    // Agregamos el registro al SE
                    $response = Http::asForm()->post('http://18.218.39.117:8000/casos/agregar', [
                        'securitytoken' => 123456,
                        'data' => json_encode($data),
                    ]);
    
                }
    
                // Actualizamos el estado SE del registro
                $detallePlanMedio->statusExperto = 1;
                if (!$detallePlanMedio->save()) {
                    array_push($detallePlanMedioNoEnviados, $detallePlanMedio);
                    continue;
                }

                array_push($detallePlanMedioEnviados, $detallePlanMedio);

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Se han enviado '.count($detallePlanMedioEnviados).' caso(s) de '.count($request->idsDetallePlanMedio).' solicitado(s) al Sistema Experto.',
                'detallePlanMedioEnviados' => $detallePlanMedioEnviados,
                'detallePlanMedioNoEnviados' => $detallePlanMedioNoEnviados,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function deleteSistemaExperto($id)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'id.exists' => 'El registro deseado no existe.',
            ];

            $params = array(
                'id' => $id,
            );

            $validator = Validator::make($params, [
                'id' => ['exists:detalle_plan_medios,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $detallePlanMedio = DetallePlanMedio::find($id);

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $data = array(
                'idsDetallePlanMedio' => "{$detallePlanMedio->id}",
            );

            // Eliminamos el registro del SE
            $response = Http::asForm()->post('http://18.218.39.117:8000/casos/eliminar', [
                'securitytoken' => 123456,
                'data' => json_encode($data),
            ]);

            // Actualizamos el estado SE del registro
            $detallePlanMedio->statusExperto = 0;
            if (!$detallePlanMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar actualizar el estado SE del registro',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los casos se han eliminado correctamente del Sistema Experto.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function deleteMassSistemaExperto(Request $request)
    {
      //
        try {
            DB::beginTransaction();

            $messages = [
                'idsDetallePlanMedio.required' => 'DPMs es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'idsDetallePlanMedio' => ['required','array'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Verificamos la conexion al SE
            $response = Http::get('http://18.218.39.117:8000');

            if (!isset($response->json()['service']) || !($response->json()['service'] == "ok")) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible la conexion al Sistema Experto.',
                ], 400);
            }

            $data = array(
                'idsDetallePlanMedio' => implode(',', $request->idsDetallePlanMedio),
            );

            // Eliminamos los registro del SE
            $response = Http::asForm()->post('http://18.218.39.117:8000/casos/eliminar', [
                'securitytoken' => 123456,
                'data' => json_encode($data),
            ]);

            DetallePlanMedio::whereIn('id', $request->idsDetallePlanMedio)->update(['statusExperto' => 0]);

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Los casos se han eliminado correctamente del Sistema Experto.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

}
