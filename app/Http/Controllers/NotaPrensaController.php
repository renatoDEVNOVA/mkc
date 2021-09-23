<?php

namespace App\Http\Controllers;

use App\NotaPrensa;
use Illuminate\Http\Request;

use App\PlanMedio;
use Illuminate\Support\Str;
use App\User;
use Validator;
use DB;
use Storage;

use Mail;
use App\Mail\NotaPrensa as NotaPrensaMail;

class NotaPrensaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $notaPrensas = NotaPrensa::with(
            'planMedios.campaign.cliente',
        )->get();

        return response()->json([
            'ready' => true,
            'notaPrensas' => $notaPrensas,
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
                'titulo.required' => 'El Titulo es obligatorio.',
                'fechaValidez.date' => 'Seleccione una Fecha de Validez valida.',
                'archivo.required' => 'El Archivo es obligatorio.',
                'archivo.mimes' => 'Solo se permiten archivos de tipo .doc y .docx.',
                'archivo.max' => 'Solo se aceptan archivos con un tamaño máximo de 2 Mb.',
            ];

            $validator = Validator::make($request->all(), [
                'titulo' => ['required'],
                'fechaValidez' => ['nullable','date'],
                'archivo' => ['required','mimes:doc,docx','max:2048'],
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
            );

            $notaPrensa = NotaPrensa::create($data);

            if (!$notaPrensa->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La Nota de Prensa no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $notaPrensa->subtitulo = isset($request->subtitulo) ? $request->subtitulo : null;
            $notaPrensa->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            $notaPrensa->observacion = isset($request->observacion) ? $request->observacion : null;
            $notaPrensa->fechaValidez = isset($request->fechaValidez) ? $request->fechaValidez : null;
            if (!$notaPrensa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La Nota de Prensa no se ha creado',
                ], 500);
            }

            if ($request->hasFile('archivo')) {

                $archivo = $request->file('archivo');

                $extension = $archivo->extension();
                $nombreArchivo = str_replace(' ', '_', $request->titulo) . '_' . 'np' . $notaPrensa->id . '_' . Str::random(8). '.' . $extension;

                // Guardar archivo
                $archivo->storeAs(
                    'notaPrensas', $nombreArchivo
                );

                $notaPrensa->nombreArchivo = $nombreArchivo;
                $notaPrensa->save();

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La Nota de Prensa se ha creado correctamente',
                'notaPrensa' => $notaPrensa,
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
     * @param  \App\NotaPrensa  $notaPrensa
     * @return \Illuminate\Http\Response
     */
    public function show(NotaPrensa $notaPrensa)
    {
        //
        if(is_null($notaPrensa)){
            return response()->json([
                'ready' => false,
                'message' => 'Nota de Prensa no encontrada',
            ], 404);
        }else{

            $notaPrensa->planMedios = $notaPrensa->planMedios()->get()->map(function($planMedio){
                $planMedio->campaign->cliente;
                return $planMedio;
            });;

            return response()->json([
                'ready' => true,
                'notaPrensa' => $notaPrensa,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\NotaPrensa  $notaPrensa
     * @return \Illuminate\Http\Response
     */
    public function edit(NotaPrensa $notaPrensa)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\NotaPrensa  $notaPrensa
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, NotaPrensa $notaPrensa)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'titulo.required' => 'El Titulo es obligatorio.',
                'fechaValidez.date' => 'Seleccione una Fecha de Validez valida.',
                'archivo.mimes' => 'Solo se permiten archivos de tipo .doc y .docx.',
                'archivo.max' => 'Solo se aceptan archivos con un tamaño máximo de 2 Mb.',
            ];

            $validator = Validator::make($request->all(), [
                'titulo' => ['required'],
                'fechaValidez' => ['nullable','date'],
                'archivo' => ['nullable','mimes:doc,docx','max:2048'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $notaPrensa->titulo = $request->titulo;
            if (!$notaPrensa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La Nota de Prensa no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $notaPrensa->subtitulo = isset($request->subtitulo) ? $request->subtitulo : null;
            $notaPrensa->descripcion = isset($request->descripcion) ? $request->descripcion : null;
            $notaPrensa->observacion = isset($request->observacion) ? $request->observacion : null;
            $notaPrensa->fechaValidez = isset($request->fechaValidez) ? $request->fechaValidez : null;
            if (!$notaPrensa->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La Nota de Prensa no se ha actualizado',
                ], 500);
            }

            if ($request->hasFile('archivo')) {

                if (!is_null($notaPrensa->nombreArchivo)) {
                    // Eliminar el archivo actual
                    Storage::delete('notaPrensas/'.$notaPrensa->nombreArchivo);
                }

                $archivo = $request->file('archivo');

                $extension = $archivo->extension();
                $nombreArchivo = str_replace(' ', '_', $request->titulo) . '_' . 'np' . $notaPrensa->id . '_' . Str::random(8). '.' . $extension;

                // Guardar el nuevo archivo
                $archivo->storeAs(
                    'notaPrensas', $nombreArchivo
                );

                $notaPrensa->nombreArchivo = $nombreArchivo;
                $notaPrensa->save();

            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La Nota de Prensa se ha actualizado correctamente',
                'notaPrensa' => $notaPrensa,
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
     * @param  \App\NotaPrensa  $notaPrensa
     * @return \Illuminate\Http\Response
     */
    public function destroy(NotaPrensa $notaPrensa)
    {
        //
        try {
            DB::beginTransaction();

            $existsPlanMedio = PlanMedio::where('idNotaPrensa', $notaPrensa->id)->exists();

            if($existsPlanMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La Nota de Prensa se encuentra relacionada con diferentes planes de medios.',
                ], 400);
            }

            if (!$notaPrensa->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La Nota de Prensa no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La Nota de Prensa se ha eliminado correctamente',
                'notaPrensa' => $notaPrensa,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    private function existsNotaPrensa($idNotaPrensa)
    {
        if(is_null($idNotaPrensa)){
            return FALSE;
        }else{
            $notaPrensa = NotaPrensa::find($idNotaPrensa);
            if(is_null($notaPrensa)){
                return FALSE;
            }else{
                return file_exists(storage_path('app/notaPrensas/') . $notaPrensa->nombreArchivo);
            }
        }
    }
    
    public function downloadFile($id)
    {
        $existsNotaPrensa = $this->existsNotaPrensa($id);
    
        if($existsNotaPrensa){
    
            $notaPrensa = NotaPrensa::find($id);
    
            $file = storage_path('app/notaPrensas/') . $notaPrensa->nombreArchivo;
    
            return response()->download($file, $notaPrensa->nombreArchivo);
    
        }else{
            //return redirect('http://agente.test/reporte/404.html');
            return response()->json([
                'ready' => false,
                'message' => 'El archivo de la Nota de Prensa no existe',
            ], 404);
        }
    
    }
    
    public function displayFile($id)
    {
        $existsNotaPrensa = $this->existsNotaPrensa($id);
    
        if($existsNotaPrensa){
    
            $notaPrensa = NotaPrensa::find($id);
    
            $file = storage_path('app/notaPrensas/') . $notaPrensa->nombreArchivo;
    
            return response()->file($file);
    
        }else{
            //return redirect('http://agente.test/reporte/404.html');
            return response()->json([
                'ready' => false,
                'message' => 'El archivo de la Nota de Prensa no existe',
            ], 404);
        }
    
    }

    public function associatePlanMedio($id, $idPlanMedio)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'id.exists' => 'La nota de prensa seleccionada no existe.',
                'idPlanMedio.exists' => 'El plan de medios seleccionado no existe.',
            ];

            $params = array(
                'id' => $id,
                'idPlanMedio' => $idPlanMedio,
            );

            $validator = Validator::make($params, [
                'id' => ['exists:nota_prensas,id'],
                'idPlanMedio' => ['exists:plan_medios,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $planMedio = PlanMedio::find($idPlanMedio);
            $planMedio->idNotaPrensa = $id;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La nota de prensa no ha sido vinculada al plan de medios deseado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La nota de prensa ha sido vinculada correctamente al plan de medios deseado',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function dissociatePlanMedio($id, $idPlanMedio)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'id.exists' => 'La nota de prensa seleccionada no existe.',
                'idPlanMedio.exists' => 'El plan de medios seleccionado no existe.',
            ];

            $params = array(
                'id' => $id,
                'idPlanMedio' => $idPlanMedio,
            );

            $validator = Validator::make($params, [
                'id' => ['exists:nota_prensas,id'],
                'idPlanMedio' => ['exists:plan_medios,id'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $planMedio = PlanMedio::find($idPlanMedio);
            $planMedio->idNotaPrensa = null;
            if (!$planMedio->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La nota de prensa no ha sido desvinculada del plan de medios deseado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La nota de prensa ha sido desvinculada correctamente del plan de medios deseado',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    
    }

    public function sendMassNotaPrensa(Request $request)
    {

        try {

            $messages = [
                'idPlanMedio.exists' => 'El plan de medios seleccionado no existe.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'destinatarios' => ['required'],
                'asunto' => ['required'],
                'mensaje' => ['required'],
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
                    'message' => 'No está autorizado para enviar las Notas de Prensa',
                ], 400);
            }

            $usuario = User::find(auth()->user()->id);
            $destinatarios = json_decode($request->destinatarios, true);
            $archivos = isset($request->archivos) ? $request->archivos : array();

            //$planMedio = PlanMedio::find($request->idPlanMedio);
            $existsNotaPrensa = $this->existsNotaPrensa($planMedio->idNotaPrensa);

            if($existsNotaPrensa){
    
                $notaPrensa = NotaPrensa::find($planMedio->idNotaPrensa);
          
                $destinatariosNotValidated = array();
                $allDestinatariosValidated = TRUE;
                
                foreach ($destinatarios as $destinatario) {
    
                    $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                        'email' => ['email'],
                    ]);

                    if ($validatorEmail->fails()) {
                        array_push($destinatariosNotValidated,$destinatario);
                        $allDestinatariosValidated = FALSE;
                    }
                }
          
                if($allDestinatariosValidated){
          
                    try {
            
                        Mail::bcc($destinatarios)
                            ->send(new NotaPrensaMail($usuario, $archivos, $notaPrensa, $request->mensaje, $request->asunto));
          
                        return response()->json([
                            'ready' => true,
                            'message' => 'Correo enviado',
                        ]);
          
                    } catch (\Exception $e) {
                        return response()->json([
                            'ready' => false,
                            'message' => 'Correo no enviado',
                        ], 500);
                    }
          
                }else{
                    return response()->json([
                        'ready' => false,
                        'message' => 'Correo(s) electronico(s) no validos',
                        'emails' => $destinatariosNotValidated
                    ], 400);
                }    
          
            }else{
                return response()->json([
                    'ready' => false,
                    'message' => 'Archivo de nota de prensa no existe',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function sendMassNotaPrensaV2(Request $request)
    {

        try {

            $messages = [
                'idPlanMedio.exists' => 'El plan de medios seleccionado no existe.',
            ];

            $validator = Validator::make($request->all(), [
                'idPlanMedio' => ['required','exists:plan_medios,id'],
                'destinatarios' => ['required'],
                'asunto' => ['required'],
                'mensaje' => ['required'],
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
                    'message' => 'No está autorizado para enviar las Notas de Prensa',
                ], 400);
            }

            $usuario = User::find(auth()->user()->id);
            $destinatarios = json_decode($request->destinatarios, true);
            $archivos = isset($request->archivos) ? $request->archivos : array();

            $existsNotaPrensa = $this->existsNotaPrensa($planMedio->idNotaPrensa);

            if($existsNotaPrensa){
    
                $notaPrensa = NotaPrensa::find($planMedio->idNotaPrensa);
          
                $destinatariosNotValidated = array();
                $allDestinatariosValidated = TRUE;
                
                foreach ($destinatarios as $destinatario) {
    
                    $validatorEmail = Validator::make(array('email' => $destinatario['email']), [
                        'email' => ['email'],
                    ]);

                    if ($validatorEmail->fails()) {
                        array_push($destinatariosNotValidated,$destinatario);
                        $allDestinatariosValidated = FALSE;
                    }
                }
          
                if($allDestinatariosValidated){
          
                    $failDestinatarios = array();
                    $successDestinatarios = array();

                    foreach ($destinatarios as $destinatario) {

                        $tries = 0;
                        $mailSent = false;
                
                        while(!$mailSent && ($tries<3)){
                
                            try {
                    
                                Mail::to($destinatario['email'])
                                    ->send(new NotaPrensaMail($usuario, $archivos, $notaPrensa, $request->mensaje, $request->asunto));
                    
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

                    return response()->json([
                        'ready' => true,
                        'message' => 'Correo enviado',
                        'successDestinatarios' => $successDestinatarios,
                        'failDestinatarios' => $failDestinatarios
                    ]);
          
                }else{
                    return response()->json([
                        'ready' => false,
                        'message' => 'Correo(s) electronico(s) no validos',
                        'emails' => $destinatariosNotValidated
                    ], 400);
                }    
          
            }else{
                return response()->json([
                    'ready' => false,
                    'message' => 'Archivo de nota de prensa no existe',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }
}
