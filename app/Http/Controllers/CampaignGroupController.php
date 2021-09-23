<?php

namespace App\Http\Controllers;

use App\CampaignGroup;
use Illuminate\Http\Request;

use App\Campaign;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class CampaignGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $campaignGroups = CampaignGroup::all();

        return response()->json([
            'ready' => true,
            'campaignGroups' => $campaignGroups,
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
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.unique' => 'Ya se encuentra registrado un grupo de campañas con el mismo nombre.',
                'fechaInicio.required' => 'La Fecha de Inicio es obligatorio.',
                'fechaInicio.date' => 'Seleccione una Fecha de Inicio valida.',
                'fechaFin.required' => 'La Fecha de Fin es obligatorio.',
                'fechaFin.date' => 'Seleccione una Fecha de Fin valida.',
                'descripcion.required' => 'La descripcion es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'unique:campaign_groups,nombre,NULL,id,deleted_at,NULL'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'descripcion' => ['required'],
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
                'nombre' => $request->nombre,
                'fechaInicio' => $request->fechaInicio,
                'fechaFin' => $request->fechaFin,
                'descripcion' => $request->descripcion,
            );

            $campaignGroup = CampaignGroup::create($data);

            if (!$campaignGroup->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El grupo de campañas no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El grupo de campañas se ha creado correctamente',
                'campaignGroup' => $campaignGroup,
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
     * @param  \App\CampaignGroup  $campaignGroup
     * @return \Illuminate\Http\Response
     */
    public function show(CampaignGroup $campaignGroup)
    {
        //
        if(is_null($campaignGroup)){
            return response()->json([
                'ready' => false,
                'message' => 'Grupo de campañas no encontrado',
            ], 404);
        }else{

            return response()->json([
                'ready' => true,
                'campaignGroup' => $campaignGroup,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CampaignGroup  $campaignGroup
     * @return \Illuminate\Http\Response
     */
    public function edit(CampaignGroup $campaignGroup)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CampaignGroup  $campaignGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CampaignGroup $campaignGroup)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.unique' => 'Ya se encuentra registrado un grupo de campañas con el mismo nombre.',
                'fechaInicio.required' => 'La Fecha de Inicio es obligatorio.',
                'fechaInicio.date' => 'Seleccione una Fecha de Inicio valida.',
                'fechaFin.required' => 'La Fecha de Fin es obligatorio.',
                'fechaFin.date' => 'Seleccione una Fecha de Fin valida.',
                'descripcion.required' => 'La descripcion es obligatorio.',
            ];

            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'unique:campaign_groups,nombre,' . $campaignGroup->id . ',id,deleted_at,NULL'],
                'fechaInicio' => ['required','date'],
                'fechaFin' => ['required','date'],
                'descripcion' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $campaignGroup->nombre = $request->nombre;
            $campaignGroup->fechaInicio = $request->fechaInicio;
            $campaignGroup->fechaFin = $request->fechaFin;
            $campaignGroup->descripcion = $request->descripcion;
            if (!$campaignGroup->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El grupo de campañas no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El grupo de campañas se ha actualizado correctamente',
                'campaignGroup' => $campaignGroup,
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
     * @param  \App\CampaignGroup  $campaignGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(CampaignGroup $campaignGroup)
    {
        //
        try {
            DB::beginTransaction();

            $existsCampaign = Campaign::where('idCampaignGroup', $campaignGroup->id)->exists();

            if($existsCampaign){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El grupo de campañas se encuentra relacionado con diferentes campañas.',
                ], 400);
            }

            if (!$campaignGroup->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El grupo de campañas no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El grupo de campañas se ha eliminado correctamente',
                'campaignGroup' => $campaignGroup,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
