<?php

namespace App\Http\Controllers;

use App\Company;
use Illuminate\Http\Request;

use App\Medio;
use App\Atributo;
use Validator;
use DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $companies = Company::with('tipoDocumento')->get();

        return response()->json([
            'ready' => true,
            'companies' => $companies,
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
                'nombreComercial.required' => 'El Nombre comercial es obligatorio.',
                'idTipoDocumento.required' => 'El Tipo de Documento es obligatorio.',
                'idTipoDocumento.exists' => 'Seleccione un Tipo de Documento valido.',
                'nroDocumento.required' => 'El Numero de Documento es obligatoria.',
                'nroDocumento.unique' => 'Ya se encuentra registrada una compañia con el mismo Tipo y Numero de Documento.',
            ];

            $validator = Validator::make($request->all(), [
                'nombreComercial' => ['required'],
                'idTipoDocumento' => [
                    'required',
                    Rule::exists('tipo_atributos','id')->where(function ($query) {
                        $atributo = Atributo::where('slug', 'document')->first();
                        $query->where('atributo_id', $atributo->id);
                    }),
                ],
                'nroDocumento' => [
                    'required',
                    Rule::unique('companies')->where(function ($query) use ($request){
                        return $query->where('idTipoDocumento', $request->idTipoDocumento)->whereNull('deleted_at');
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
                'nombreComercial' => $request->nombreComercial,
                'idTipoDocumento' => $request->idTipoDocumento,
                'nroDocumento' => $request->nroDocumento,
            );

            $company = Company::create($data);

            if (!$company->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La compañia no se ha creado',
                ], 500);
            }

            // Datos Opcionales
            $company->razonSocial = isset($request->razonSocial) ? $request->razonSocial : null;
            $company->propietario = isset($request->propietario) ? $request->propietario : null;
            $company->representanteLegal = isset($request->representanteLegal) ? $request->representanteLegal : null;
            if (!$company->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La compañia no se ha creado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La compañia se ha creado correctamente',
                'company' => $company,
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
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function show(Company $company)
    {
        //
        if(is_null($company)){
            return response()->json([
                'ready' => false,
                'message' => 'Compañia no encontrada',
            ], 404);
        }else{

            $company->tipoDocumento;

            return response()->json([
                'ready' => true,
                'company' => $company,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Company $company)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombreComercial.required' => 'El Nombre comercial es obligatorio.',
                'idTipoDocumento.required' => 'El Tipo de Documento es obligatorio.',
                'idTipoDocumento.exists' => 'Seleccione un Tipo de Documento valido.',
                'nroDocumento.required' => 'El Numero de Documento es obligatoria.',
                'nroDocumento.unique' => 'Ya se encuentra registrada una compañia con el mismo Tipo y Numero de Documento.',
            ];

            $validator = Validator::make($request->all(), [
                'nombreComercial' => ['required'],
                'idTipoDocumento' => [
                    'required',
                    Rule::exists('tipo_atributos','id')->where(function ($query) {
                        $atributo = Atributo::where('slug', 'document')->first();
                        $query->where('atributo_id', $atributo->id);
                    }),
                ],
                'nroDocumento' => [
                    'required',
                    Rule::unique('companies')->ignore($company->id)->where(function ($query) use ($request){
                        return $query->where('idTipoDocumento', $request->idTipoDocumento)->whereNull('deleted_at');
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
            $company->nombreComercial = $request->nombreComercial;
            $company->idTipoDocumento = $request->idTipoDocumento;
            $company->nroDocumento = $request->nroDocumento;
            if (!$company->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La compañia no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $company->razonSocial = isset($request->razonSocial) ? $request->razonSocial : null;
            $company->propietario = isset($request->propietario) ? $request->propietario : null;
            $company->representanteLegal = isset($request->representanteLegal) ? $request->representanteLegal : null;
            if (!$company->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La compañia no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La compañia se ha actualizado correctamente',
                'company' => $company,
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
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function destroy(Company $company)
    {
        //
        try {
            DB::beginTransaction();

            $existsMedio = Medio::where('company_id', $company->id)->exists();

            if($existsMedio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. La compañia se encuentra relacionada con diferentes medios.',
                ], 400);
            }

            if (!$company->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'La compañia no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'La compañia se ha eliminado correctamente',
                'company' => $company,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
