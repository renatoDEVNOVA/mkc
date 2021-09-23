<?php

namespace App\Http\Controllers;

use App\PlanMedio;
use Illuminate\Http\Request;

use App\Campaign;
use App\Cliente;
use App\DetallePlanMedio;
use App\ProgramaContacto;
use App\Bitacora;
use App\Http\Resources\DetallePlanMedioCollection;
use App\Http\Resources\PlanMedioCollection;
use App\Http\Resources\PlanMedioDetailCollection;
use App\Http\Resources\PlanMedioEstadoCollection;
use App\MedioPlataforma;
use App\Registro;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Validator;
use DB;
use Storage;
use Illuminate\Database\Eloquent\Builder;

class PlanMedioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $idsCampaign = auth()->user()->myCampaigns();

        $planMedios = PlanMedio::with(
            'campaign.cliente',
            'detallePlanMedios',
        )->get()->map(function($planMedio) use ($idsCampaign){
            $planMedio->isEditable = in_array($planMedio->campaign_id, $idsCampaign);
            return $planMedio;
        });       

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios,
        ]);
    }

    public function planMediosSelect()
    {
        $planMedios = PlanMedio::select('nombre','id')->get();
        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios,
        ]);
    }

    public function dataList(){
        $idsCampaign = auth()->user()->myCampaigns();

        $query = request('search-plan-medio', );

        if ($query !== 'null' && trim($query) !== '') {
            $planMedio = PlanMedio::search($query)->with('campaign.cliente')->orderBy('id', 'DESC');
        } else {
            $planMedio = PlanMedio::orderBy('id', 'DESC');
        }

        if(auth()->user()->hasAnyRole(['admin','super-admin'])){
            $planMedios = $planMedio;
        }else{
            $planMedios = $planMedio->whereIn('campaign_id', $idsCampaign);
        }
        return new PlanMedioDetailCollection($planMedios->paginate(10));
    }
    
    public function dataListEstado($estado){

        $idsCampaign = auth()->user()->myCampaigns();

        $query = request('search-plan-medio', );

        if ($query !== 'null' && trim($query) !== '') {
            $planMedio = PlanMedio::with(
                'campaign.cliente',
                'detallePlanMedios',
            )->search($query)->with('campaign.cliente')->orderBy('id', 'DESC');
        } else {
            $planMedio = PlanMedio::with(
                'campaign.cliente',
                'detallePlanMedios',
            )->orderBy('id', 'DESC');
        }

        if(auth()->user()->hasAnyRole(['admin','super-admin'])){
            $planMedios = $planMedio;
        }else{
            $planMedios = $planMedio->whereIn('campaign_id', $idsCampaign);
        }
        
        return new PlanMedioDetailCollection($planMedios->where('status',$estado)->paginate(10));
    }

    public function dataListDetalles(){ 
        $query = request('search-plan-medio', );
        if ($query !== 'null' && trim($query) !== '') {
            $planMedio = PlanMedio::search($query)->with('campaign.cliente')->orderBy('id', 'DESC');
        } else {
            $planMedio = PlanMedio::orderBy('id', 'DESC');
        }
        $planMedios = new PlanMedioDetailCollection($planMedio->paginate(10));
        return $planMedios;
    }

    public function planMedioDetails($planMedio_id){
        $detallePlanMedios = new DetallePlanMedioCollection(DetallePlanMedio::where('idPlanMedio', $planMedio_id)->get());
        return $detallePlanMedios;
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
                'nombre.required' => 'El Nombre es obligatorio.',
                'campaign_id.required' => 'La Campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una Campaña valida.',
                'idNotaPrensa.exists' => 'Seleccione una Nota de Prensa valida.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required'],
                'campaign_id' => ['required','exists:campaigns,id'],
                'idNotaPrensa' => ['nullable','exists:nota_prensas,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($request->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un plan de medios para la campaña deseada',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'nombre' => $request->nombre,
                'campaign_id' => $request->campaign_id,
                'status' => 0,
                'user_id' => auth()->user()->id,
            );

            $planMedio = PlanMedio::create($data);

            if (!$planMedio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha creado',
                ], 500);
            }

            // Crear directorio del plan de medio
            $campaign = Campaign::find($planMedio->campaign_id);
            $cliente = Cliente::find($campaign->cliente_id);

            Storage::makeDirectory('clientes/'.$cliente->alias.'/'.$campaign->alias.'/pm'.$planMedio->id);


            // Datos Opcionales
            $planMedio->idNotaPrensa = isset($request->idNotaPrensa) ? $request->idNotaPrensa : null;
            $planMedio->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha creado',
                ], 500);
            }

            $registro = $this->generateRegistro($planMedio->id);
            if (!$registro->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear un registro',
                ], 500);
            }

            if (isset($request->detallePlanMedios)) {

                $detallePlanMedios = json_decode(json_encode($request->detallePlanMedios));

                $messagesDetallePlanMedios = [
                    'detallePlanMedios.*.idProgramaContacto.required' => 'Contacto/Programa es obligatorio para cada registro.',
                    'detallePlanMedios.*.idProgramaContacto.distinct' => 'Selecione Contacto/Programa diferentes para cada registro.',
                    'detallePlanMedios.*.idProgramaContacto.exists' => 'Selecione Contacto/Programa validos para cada registro.',
                    'detallePlanMedios.*.idsMedioPlataforma.required' => 'Plataformas es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoTier.in' => 'Selecione Tier validos para cada registro.',
                    'detallePlanMedios.*.tipoNota.required' => 'Tipo de Nota es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoNota.in' => 'Selecione Tipo de Nota validos para cada registro.',
                    'detallePlanMedios.*.tipoEtapa.required' => 'Etapa es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoEtapa.in' => 'Selecione Etapa validos para cada registro.',
                    'detallePlanMedios.*.muestrasRegistradas.required' => 'El numero de muestras registradas es obligatorio para cada registro.',
                    'detallePlanMedios.*.muestrasEnviadas.required' => 'El numero de muestras enviadas es obligatorio para cada registro.',
                    'detallePlanMedios.*.muestrasVerificadas.required' => 'El numero de muestras verificadas es obligatorio para cada registro.',
                    'detallePlanMedios.*.voceros.required' => 'Voceros es obligatorio para cada registro.',
                ];

                $validatorDetallePlanMedios = Validator::make($request->only('detallePlanMedios'), [
                    'detallePlanMedios' => ['nullable','array'],
                    'detallePlanMedios.*.idProgramaContacto' => ['required','distinct','exists:programa_contactos,id'],
                    'detallePlanMedios.*.idsMedioPlataforma' => ['required','array'],
                    'detallePlanMedios.*.tipoTier' => [
                        'nullable',
                        Rule::in([1, 2, 3]),
                    ],
                    'detallePlanMedios.*.tipoNota' => [
                        'required',
                        Rule::in([1, 2, 3, 4]),
                    ],
                    'detallePlanMedios.*.tipoEtapa' => [
                        'required',
                        Rule::in([1, 2, 3]),
                    ],
                    'detallePlanMedios.*.muestrasRegistradas' => ['required','integer'],
                    'detallePlanMedios.*.muestrasEnviadas' => ['required','integer'],
                    'detallePlanMedios.*.muestrasVerificadas' => ['required','integer'],
                    //'detallePlanMedios.*.voceros' => ['required','array'],
                    'detallePlanMedios.*.voceros' => ['array'],
                ], $messagesDetallePlanMedios);

                if ($validatorDetallePlanMedios->fails()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorDetallePlanMedios->errors(),
                    ], 400);
                }

                foreach ($detallePlanMedios as $detallePlanMedio) {
                    # code...

                    $idsMedioPlataformaIn = ProgramaContacto::find($detallePlanMedio->idProgramaContacto)->idsMedioPlataforma;

                    $messagesDetallePlanMedio = [
                        'idsMedioPlataforma.*.in' => 'Seleccione plataformas validas.',
                        'voceros.*.exists' => 'Seleccione voceros validas.',
                    ];
    
                    $validatorDetallePlanMedio = Validator::make(array(
                        'idsMedioPlataforma' => $detallePlanMedio->idsMedioPlataforma,
                        'voceros' => $detallePlanMedio->voceros
                    ), [
                        'idsMedioPlataforma.*' =>['in:'.$idsMedioPlataformaIn],
                        'voceros.*' => [
                            Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($planMedio){
                                $query->where('campaign_id', $planMedio->campaign_id);
                            }),
                        ],
                    ], $messagesDetallePlanMedio);

                    if ($validatorDetallePlanMedio->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorDetallePlanMedio->errors(),
                            'detallePlanMedio' => $detallePlanMedio,
                        ], 400);
                    }

                    $dataDetallePlanMedio = array(
                        'idPlanMedio' => $planMedio->id,
                        'idProgramaContacto' => $detallePlanMedio->idProgramaContacto,
                        'idsMedioPlataforma' => implode(',', $detallePlanMedio->idsMedioPlataforma),
                        'tipoTier' => isset($detallePlanMedio->tipoTier) ? $detallePlanMedio->tipoTier : null,
                        'tipoNota' => $detallePlanMedio->tipoNota,
                        'tipoEtapa' => $detallePlanMedio->tipoEtapa,
                        'muestrasRegistradas' => $detallePlanMedio->muestrasRegistradas,
                        'muestrasEnviadas' => $detallePlanMedio->muestrasEnviadas,
                        'muestrasVerificadas' => $detallePlanMedio->muestrasVerificadas,
                        'statusPublicado' => 0,
                        'statusExperto' => 0,
                        'vinculado' => 0,
                        'idDetallePlanMedioPadre' => null,
                        'user_id' => $planMedio->user_id,
                    );

                    $DPM = DetallePlanMedio::create($dataDetallePlanMedio);
                    if (!$DPM->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El plan de medio no se ha creado',
                        ], 500);
                    }

                    $DPM->voceros()->sync($detallePlanMedio->voceros);

                    $bitacora = $this->generateBitacora($DPM->id);
                    if (!$bitacora->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Error al intentar crear una bitacora',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El plan de medio se ha creado correctamente',
                'planMedio' => $planMedio,
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
                'nombre.required' => 'El Nombre es obligatorio.',
                'campaign_id.required' => 'La Campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una Campaña valida.',
                'idNotaPrensa.exists' => 'Seleccione una Nota de Prensa valida.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required'],
                'campaign_id' => ['required','exists:campaigns,id'],
                'idNotaPrensa' => ['nullable','exists:nota_prensas,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($request->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para crear un plan de medios para la campaña deseada',
                ], 400);
            }

            // Datos Obligatorios
            $data = array(
                'nombre' => $request->nombre,
                'campaign_id' => $request->campaign_id,
                'status' => 0,
                'user_id' => auth()->user()->id,
            );

            $planMedio = PlanMedio::create($data);

            if (!$planMedio->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha creado',
                ], 500);
            }

            // Crear directorio del plan de medio
            $campaign = Campaign::find($planMedio->campaign_id);
            $cliente = Cliente::find($campaign->cliente_id);

            Storage::makeDirectory('clientes/'.$cliente->alias.'/'.$campaign->alias.'/pm'.$planMedio->id);


            // Datos Opcionales
            $planMedio->idNotaPrensa = isset($request->idNotaPrensa) ? $request->idNotaPrensa : null;
            $planMedio->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha creado',
                ], 500);
            }

            $registro = $this->generateRegistro($planMedio->id);
            if (!$registro->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear un registro',
                ], 500);
            }

            if (isset($request->detallePlanMedios)) {

                $detallePlanMedios = json_decode(json_encode($request->detallePlanMedios));

                $messagesDetallePlanMedios = [
                    'detallePlanMedios.*.idProgramaContacto.required' => 'Contacto/Programa es obligatorio para cada registro.',
                    'detallePlanMedios.*.idProgramaContacto.distinct' => 'Selecione Contacto/Programa diferentes para cada registro.',
                    'detallePlanMedios.*.idProgramaContacto.exists' => 'Selecione Contacto/Programa validos para cada registro.',
                    'detallePlanMedios.*.idsMedioPlataforma.required' => 'Plataformas es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoTier.in' => 'Selecione Tier validos para cada registro.',
                    'detallePlanMedios.*.tipoNota.required' => 'Tipo de Nota es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoNota.in' => 'Selecione Tipo de Nota validos para cada registro.',
                    'detallePlanMedios.*.tipoEtapa.required' => 'Etapa es obligatorio para cada registro.',
                    'detallePlanMedios.*.tipoEtapa.in' => 'Selecione Etapa validos para cada registro.',
                    'detallePlanMedios.*.muestrasRegistradas.required' => 'El numero de muestras registradas es obligatorio para cada registro.',
                    'detallePlanMedios.*.muestrasEnviadas.required' => 'El numero de muestras enviadas es obligatorio para cada registro.',
                    'detallePlanMedios.*.muestrasVerificadas.required' => 'El numero de muestras verificadas es obligatorio para cada registro.',
                    'detallePlanMedios.*.voceros.required' => 'Voceros es obligatorio para cada registro.',
                ];

                $validatorDetallePlanMedios = Validator::make($request->only('detallePlanMedios'), [
                    'detallePlanMedios' => ['nullable','array'],
                    'detallePlanMedios.*.idProgramaContacto' => ['required','distinct','exists:programa_contactos,id'],
                    'detallePlanMedios.*.idsMedioPlataforma' => ['required','array'],
                    'detallePlanMedios.*.tipoTier' => [
                        'nullable',
                        Rule::in([1, 2, 3]),
                    ],
                    'detallePlanMedios.*.tipoNota' => [
                        'required',
                        Rule::in([1, 2, 3, 4]),
                    ],
                    'detallePlanMedios.*.tipoEtapa' => [
                        'required',
                        Rule::in([1, 2, 3]),
                    ],
                    'detallePlanMedios.*.muestrasRegistradas' => ['required','integer'],
                    'detallePlanMedios.*.muestrasEnviadas' => ['required','integer'],
                    'detallePlanMedios.*.muestrasVerificadas' => ['required','integer'],
                    'detallePlanMedios.*.observacion' => ['required'],
                    'detallePlanMedios.*.voceros' => ['array'],
                ], $messagesDetallePlanMedios);

                if ($validatorDetallePlanMedios->fails()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorDetallePlanMedios->errors(),
                    ], 400);
                }

                foreach ($detallePlanMedios as $detallePlanMedio) {
                    # code...

                    $idsMedioPlataformaIn = ProgramaContacto::find($detallePlanMedio->idProgramaContacto)->idsMedioPlataforma;

                    $messagesDetallePlanMedio = [
                        'idsMedioPlataforma.*.in' => 'Seleccione plataformas validas.',
                        'voceros.*.exists' => 'Seleccione voceros validas.',
                    ];
    
                    $validatorDetallePlanMedio = Validator::make(array(
                        'idsMedioPlataforma' => $detallePlanMedio->idsMedioPlataforma,
                        'voceros' => $detallePlanMedio->voceros
                    ), [
                        'idsMedioPlataforma.*' =>['in:'.$idsMedioPlataformaIn],
                        'voceros.*' => [
                            Rule::exists('campaign_voceros','idVocero')->where(function ($query) use ($planMedio){
                                $query->where('campaign_id', $planMedio->campaign_id);
                            }),
                        ],
                    ], $messagesDetallePlanMedio);

                    if ($validatorDetallePlanMedio->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorDetallePlanMedio->errors(),
                            'detallePlanMedio' => $detallePlanMedio,
                        ], 400);
                    }

                    $dataDetallePlanMedio = array(
                        'idPlanMedio' => $planMedio->id,
                        'idProgramaContacto' => $detallePlanMedio->idProgramaContacto,
                        'idsMedioPlataforma' => implode(',', $detallePlanMedio->idsMedioPlataforma),
                        'tipoTier' => isset($detallePlanMedio->tipoTier) ? $detallePlanMedio->tipoTier : null,
                        'tipoNota' => $detallePlanMedio->tipoNota,
                        'tipoEtapa' => $detallePlanMedio->tipoEtapa,
                        'muestrasRegistradas' => $detallePlanMedio->muestrasRegistradas,
                        'muestrasEnviadas' => $detallePlanMedio->muestrasEnviadas,
                        'muestrasVerificadas' => $detallePlanMedio->muestrasVerificadas,
                        'statusPublicado' => 0,
                        'statusExperto' => 0,
                        'vinculado' => 0,
                        'idDetallePlanMedioPadre' => null,
                        'user_id' => $planMedio->user_id,
                    );

                    $DPM = DetallePlanMedio::create($dataDetallePlanMedio);
                    if (!$DPM->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El plan de medio no se ha creado',
                        ], 500);
                    }

                    $DPM->voceros()->sync($detallePlanMedio->voceros);

                    $bitacora = $this->generateBitacoraV2($DPM->id, $detallePlanMedio->observacion);
                    if (!$bitacora->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Error al intentar crear una bitacora',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El plan de medio se ha creado correctamente',
                'planMedio' => $planMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    private function generateRegistro($idPlanMedio) 
    {

        $data = array(
            'idPlanMedio' => $idPlanMedio,
            'status' => 0,
            'observacion' => 'Nuevo',
            'user_id' => auth()->user()->id,
        );

        $registro = Registro::create($data);

        return $registro;
    }

    private function generateBitacora($idDetallePlanMedio) 
    {

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
     * @param  \App\PlanMedio  $planMedio
     * @return \Illuminate\Http\Response
     */
    public function show(PlanMedio $planMedio)
    {
        //
        if(is_null($planMedio)){
            return response()->json([
                'ready' => false,
                'message' => 'Plan de medio no encontrado',
            ], 404);
        }else{

            $idsCampaign = auth()->user()->myCampaigns();

            $planMedio->campaign->cliente;
            $planMedio->campaign->campaignResponsables;
            $planMedio->notaPrensa;
            $planMedio->detallePlanMedios = $planMedio->detallePlanMedios()->get()->map(function($detallePlanMedio) use ($idsCampaign){
                $detallePlanMedio->user;
                $detallePlanMedio->programaContacto->programa->medio;
                $detallePlanMedio->programaContacto->contacto;
                $idsMedioPlataforma = explode(',', $detallePlanMedio->idsMedioPlataforma);
                $detallePlanMedio->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
                    $medioPlataforma->plataformaClasificacion->plataforma;
                    return $medioPlataforma;
                });
                $detallePlanMedio->voceros;
                $detallePlanMedio->bitacoras;
                if($detallePlanMedio->vinculado){
                    $detallePlanMedio->detallePlanMedioPadre = $this->getDetallePlanMedioPadre($detallePlanMedio->idDetallePlanMedioPadre);
                }
                $detallePlanMedio->hasAssociated = DetallePlanMedio::where('idDetallePlanMedioPadre', $detallePlanMedio->id)->exists();
                $detallePlanMedio->isEditable = in_array($detallePlanMedio->planMedio->campaign_id, $idsCampaign);
                return $detallePlanMedio;
            });
            $planMedio->isEditable = in_array($planMedio->campaign_id, $idsCampaign);
            $planMedio->registros;

            return response()->json([
                'ready' => true,
                'planMedio' => $planMedio,
            ]);
        }
    }

    private function getDetallePlanMedioPadre($idDetallePlanMedioPadre)
    {
        $detallePlanMedioPadre = DetallePlanMedio::find($idDetallePlanMedioPadre);
        $detallePlanMedioPadre->programaContacto->programa->medio;
        $detallePlanMedioPadre->programaContacto->contacto;
        $idsMedioPlataforma = explode(',', $detallePlanMedioPadre->idsMedioPlataforma);
        $detallePlanMedioPadre->medioPlataformas = MedioPlataforma::whereIn('id', $idsMedioPlataforma)->get()->map(function($medioPlataforma){
            $medioPlataforma->plataformaClasificacion->plataforma;
            return $medioPlataforma;
        });
        $detallePlanMedioPadre->voceros;
        $detallePlanMedioPadre->bitacoras;

        return $detallePlanMedioPadre;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PlanMedio  $planMedio
     * @return \Illuminate\Http\Response
     */
    public function edit(PlanMedio $planMedio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PlanMedio  $planMedio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PlanMedio $planMedio)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombre.required' => 'El Nombre es obligatorio.',
                'campaign_id.required' => 'La Campaña es obligatoria.',
                'campaign_id.exists' => 'Seleccione una Campaña valida.',
                'idNotaPrensa.exists' => 'Seleccione una Nota de Prensa valida.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required'],
                //'campaign_id' => ['required','exists:campaigns,id'],
                'idNotaPrensa' => ['nullable','exists:nota_prensas,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $idsCampaign = auth()->user()->myCampaigns();

            if(!auth()->user()->hasAnyRole(['admin','super-admin']) && !in_array($planMedio->campaign_id, $idsCampaign)){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el plan de medios',
                ], 400);
            }

            /*if(!auth()->user()->hasAnyRole(['admin','super-admin']) && $planMedio->user_id != auth()->user()->id){
                return response()->json([
                    'ready' => false,
                    'message' => 'No está autorizado para actualizar el plan de medios',
                ], 400);
            }*/

            //$oldCampaign = Campaign::find($planMedio->campaign_id);
            //$oldCliente = Cliente::find($oldCampaign->cliente_id);

            // Datos Obligatorios
            $planMedio->nombre = $request->nombre;
            //$planMedio->campaign_id = $request->campaign_id;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha actualizado',
                ], 500);
            }

            // Mover directorio del plan de medio
            //$newCampaign = Campaign::find($planMedio->campaign_id);
            //$newCliente = Cliente::find($newCampaign->cliente_id);

            /*if($oldCampaign->id != $newCampaign->id){
                Storage::move('clientes/'.$oldCliente->alias.'/'.$oldCampaign->alias.'/pm'.$planMedio->id, 'clientes/'.$newCliente->alias.'/'.$newCampaign->alias.'/pm'.$planMedio->id);
            }*/

            // Datos Opcionales
            $planMedio->idNotaPrensa = isset($request->idNotaPrensa) ? $request->idNotaPrensa : null;
            $planMedio->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha actualizado',
                ], 500);
            }


            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El plan de medio se ha actualizado correctamente',
                'planMedio' => $planMedio,
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
     * @param  \App\PlanMedio  $planMedio
     * @return \Illuminate\Http\Response
     */
    public function destroy(PlanMedio $planMedio)
    {
        //
        try {
            DB::beginTransaction();

            $existsDetallePlanMedio = DetallePlanMedio::where('idPlanMedio', $planMedio->id)->exists();

            if($existsDetallePlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El plan de medio se encuentra relacionado con diferentes DPM.',
                ], 400);
            }

            if (!$planMedio->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El plan de medio no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El plan de medio se ha eliminado correctamente',
                'planMedio' => $planMedio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByLogged()
    {
        $idsCampaign = auth()->user()->myCampaigns();

        /*$planMedios = PlanMedio::with(
            'campaign.cliente',
            'detallePlanMedios',
        )->whereIn('campaign_id', $idsCampaign)->get();*/

        if(auth()->user()->hasAnyRole(['admin','super-admin'])){

            $planMedios = PlanMedio::with(
                'campaign.cliente',
                'detallePlanMedios',
            )->get();

        }else{

            $planMedios = PlanMedio::with(
                'campaign.cliente',
                'detallePlanMedios',
            )->whereIn('campaign_id', $idsCampaign)->get();

        }

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios->values(),
        ]);
    }

    public function getListByLoggedAndDates(Request $request)
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

                $planMedios = PlanMedio::with(
                    'campaign.cliente',
                    'detallePlanMedios',
                )->whereDate('plan_medios.created_at', '>=', $fechaInicio)
                ->whereDate('plan_medios.created_at', '<=', $fechaFin)
                ->get();

            }else{

                $planMedios = PlanMedio::with(
                    'campaign.cliente',
                    'detallePlanMedios',
                )->whereDate('plan_medios.created_at', '>=', $fechaInicio)
                ->whereDate('plan_medios.created_at', '<=', $fechaFin)
                ->where(function ($query) use ($idsCampaign){
                    $query->whereHas('detallePlanMedios', function (Builder $query) {
                        $query->where('detalle_plan_medios.user_id', auth()->user()->id);
                    })->orWhereIn('plan_medios.campaign_id', $idsCampaign);
                })
                ->get();

            }

            $planMedios->map(function($planMedio) use ($idsCampaign){
                $planMedio->isEditable = in_array($planMedio->campaign_id, $idsCampaign);
                return $planMedio;
            });

            return response()->json([
                'ready' => true,
                'planMedios' => $planMedios->values(),
                'count' => $planMedios->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByEstado($estado)
    {
        $planMedios = PlanMedio::with(
            'campaign.cliente',
            'detallePlanMedios',
        )->where('status', $estado)->get();

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios->values(),
        ]);
    }

    public function changeStatus(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'idPlanMedio.required' => 'El Plan de Medios es obligatoria.',
                'idPlanMedio.exists' => 'Seleccione una Plan de Medios valida.',
                'status.required' => 'Estado es obligatorio.',
                'status.in' => 'Selecione Estado valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'status' => [
                    'required',
                    Rule::in([0, 1, 2]),
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $planMedio = PlanMedio::find($request->idPlanMedio);

            if($request->status < $planMedio->status){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de pasar a un estado anterior al actual',
                ], 400);
            }

            $planMedio->status = $request->status;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El estado del plan de medios no se ha actualizado',
                ], 500);
            }


            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El estado del plan de medios se ha actualizado correctamente',
                'planMedio' => $planMedio,
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
        $planMedios = PlanMedio::where('campaign_id', $idCampaign)->get();

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios->values(),
        ]);
    }

    public function getListByCliente($idCliente)
    {
        $planMedios = PlanMedio::with('campaign.cliente')->whereHas('campaign', function (Builder $query) use ($idCliente){
            $query->where('cliente_id', $idCliente);
        })->get();

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios->values(),
        ]);
    }


    public function getList()
    {
        $planMedios = PlanMedio::all();

        return response()->json([
            'ready' => true,
            'planMedios' => $planMedios,
        ]);
    }

}
