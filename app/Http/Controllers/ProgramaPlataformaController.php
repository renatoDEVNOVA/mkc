<?php

namespace App\Http\Controllers;

use App\ProgramaPlataforma;
use Illuminate\Http\Request;

use App\ProgramaContacto;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class ProgramaPlataformaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $programaPlataformas = ProgramaPlataforma::all();

        return response()->json([
            'ready' => true,
            'programaPlataformas' => $programaPlataformas,
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
                'idMedioPlataforma.required' => 'La plataforma del medio es obligatorio.',
                'idMedioPlataforma.exists' => 'Seleccione una plataforma del medio valida.',
                'idMedioPlataforma.unique' => 'Ya se encuentra registrada la plataforma del medio deseada.',
                'programa_id.required' => 'El programa es obligatorio.',
                'programa_id.exists' => 'Seleccione un programa valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idMedioPlataforma' => [
                    'required',
                    'exists:medio_plataformas,id',
                    Rule::unique('programa_plataformas')->where(function ($query) use ($request){
                        return $query->where('programa_id', $request->programa_id)->whereNull('deleted_at');
                    }),
                ],
                'programa_id' => ['required','exists:programas,id'],
                'valor' => ['nullable','numeric'],
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
                'programa_id' => $request->programa_id,
                'idMedioPlataforma' => $request->idMedioPlataforma,
            );

            $programaPlataforma = ProgramaPlataforma::create($data);

            if (!$programaPlataforma->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del programa no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $programaPlataforma->valor = isset($request->valor) ? $request->valor : null;
            if (!$programaPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del programa no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del programa se ha creado correctamente',
                'programaPlataforma' => $programaPlataforma,
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
     * @param  \App\ProgramaPlataforma  $programaPlataforma
     * @return \Illuminate\Http\Response
     */
    public function show(ProgramaPlataforma $programaPlataforma)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ProgramaPlataforma  $programaPlataforma
     * @return \Illuminate\Http\Response
     */
    public function edit(ProgramaPlataforma $programaPlataforma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ProgramaPlataforma  $programaPlataforma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProgramaPlataforma $programaPlataforma)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'idMedioPlataforma.required' => 'La plataforma del medio es obligatorio.',
                'idMedioPlataforma.exists' => 'Seleccione una plataforma del medio valida.',
                'idMedioPlataforma.unique' => 'Ya se encuentra registrada la plataforma del medio deseada.',
                'programa_id.required' => 'El programa es obligatorio.',
                'programa_id.exists' => 'Seleccione un programa valido.',
            ];

            $validator = Validator::make($request->all(), [
                'idMedioPlataforma' => [
                    'required',
                    'exists:medio_plataformas,id',
                    Rule::unique('programa_plataformas')->ignore($programaPlataforma->id)->where(function ($query) use ($request){
                        return $query->where('programa_id', $request->programa_id)->whereNull('deleted_at');
                    }),
                ],
                'programa_id' => ['required','exists:programas,id'],
                'valor' => ['nullable','numeric'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Datos Obligatorios
            $programaPlataforma->programa_id = $request->programa_id;
            $programaPlataforma->idMedioPlataforma = $request->idMedioPlataforma;
            if (!$programaPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del programa no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $programaPlataforma->valor = isset($request->valor) ? $request->valor : null;
            if (!$programaPlataforma->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del programa no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del programa se ha actualizado correctamente',
                'programaPlataforma' => $programaPlataforma,
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
     * @param  \App\ProgramaPlataforma  $programaPlataforma
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProgramaPlataforma $programaPlataforma)
    {
        //
        try {
            DB::beginTransaction();

            $countProgramaContacto = ProgramaContacto::where('programa_id', $programaPlataforma->programa_id)->get()->filter(function ($programaContacto) use ($programaPlataforma){
                $idsMedioPlataforma = explode(',', $programaContacto->idsMedioPlataforma);
                return in_array($programaPlataforma->idMedioPlataforma, $idsMedioPlataforma);
            })->count();

            if($countProgramaContacto > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La plataforma del programa se encuentra relacionada con diferentes contactos.',
                ], 400);
            }

            if (!$programaPlataforma->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La plataforma del programa no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La plataforma del programa se ha eliminado correctamente',
                'programaPlataforma' => $programaPlataforma,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByPrograma($idPrograma)
    {
        $programaPlataformas = ProgramaPlataforma::with('programa','medioPlataforma.plataformaClasificacion.plataforma')->whereNull('deleted_at')->get()->filter(function ($programaPlataforma) use ($idPrograma){
            return $programaPlataforma->programa_id == $idPrograma;
        });

        return response()->json([
            'ready' => true,
            'programaPlataformas' => $programaPlataformas->values(),
        ]);
    }
}
