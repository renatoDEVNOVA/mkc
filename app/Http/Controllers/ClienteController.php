<?php

namespace App\Http\Controllers;

use App\Cliente;
use Illuminate\Http\Request;

use App\ClienteEmail;
use App\ClienteTelefono;
use App\ClienteEncargado;
use App\ClienteVocero;
use App\Campaign;
use App\Atributo;
use App\User;
use Validator;
use DB;
use Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Mail;
use App\Mail\ClienteCredenciales as ClienteCredencialesMail;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $clientes = Cliente::with(
            'tipoDocumento',
            'emails.tipoEmail',
            'telefonos.tipoTelefono',
            'users',
            'envios',
            'reportes'
        )->get();

        return response()->json([
            'ready' => true,
            'clientes' => $clientes,
        ]);
    }

    public function clienteSelect()
    {
        $clientes = Cliente::select('nombreComercial','id')->get();
        return response()->json([
            'ready' => true,
            'clientes' => $clientes,
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
                'nroDocumento.unique' => 'Ya se encuentra registrado un cliente con el mismo Tipo y Numero de Documento.',
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
                    Rule::unique('clientes')->where(function ($query) use ($request){
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

            $cliente = Cliente::create($data);

            if (!$cliente->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha creado',
                ], 500);
            }
            
            $cliente->alias = $cliente->id . '_' . Str::random(8);
            if (!$cliente->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha creado',
                ], 500);
            }

            // Crear directorio del cliente
            Storage::makeDirectory('clientes/'.$cliente->alias);

            // Datos Opcionales
            $cliente->razonSocial = isset($request->razonSocial) ? $request->razonSocial : null;
            $cliente->rubro = isset($request->rubro) ? $request->rubro : null;
            $cliente->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$cliente->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha creado',
                ], 500);
            }

            if ($request->hasFile('logo')) {

                $messagesLogo = [
                    'logo.file' => 'El logo debe ser un archivo.',
                    'logo.mimes' => 'Solo se permiten archivos de tipo .png,.jpg y .jpeg.',
                ];

                $validatorLogo = Validator::make($request->only('logo'), [
                    'logo' => ['file', 'mimes:png,jpg,jpeg'],
                ], $messagesLogo);

                if ($validatorLogo->fails()) {
                    //Storage::deleteDirectory('clientes/'.$cliente->alias);
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorLogo->errors(),
                    ], 400);
                }

                $logo = $request->file('logo');

                $extension = $logo->extension();
                $nameLogo = 'logo_' . Str::random(8). '.' . $extension;

                // Guardar logo
                $logo->storeAs(
                    'clientes/'.$cliente->alias, $nameLogo
                );

                $cliente->logo = $nameLogo;
                $cliente->save();

            }

            if (isset($request->cliente_emails)) {

                $cliente_emails = json_decode($request->cliente_emails);

                foreach ($cliente_emails as $cliente_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$cliente_email->email.' se encuentra duplicado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $cliente_email->email), [
                        'email' => [
                            'email',
                            Rule::unique('cliente_emails')->where(function ($query) use ($cliente){
                                return $query->where('cliente_id', $cliente->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'cliente_email' => $cliente_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'cliente_id' => $cliente->id,
                        'email' => $cliente_email->email,
                        'idTipoEmail' => $cliente_email->idTipoEmail,
                    );

                    $clienteEmail = ClienteEmail::create($dataEmail);
                    if (!$clienteEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El cliente no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->cliente_telefonos)) {

                $cliente_telefonos = json_decode($request->cliente_telefonos);

                foreach ($cliente_telefonos as $cliente_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$cliente_telefono->telefono.' se encuentra duplicado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $cliente_telefono->telefono), [
                        'telefono' => [
                            Rule::unique('cliente_telefonos')->where(function ($query) use ($cliente){
                                return $query->where('cliente_id', $cliente->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'cliente_telefono' => $cliente_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'cliente_id' => $cliente->id,
                        'telefono' => $cliente_telefono->telefono,
                        'idTipoTelefono' => $cliente_telefono->idTipoTelefono,
                    );

                    $clienteTelefono = ClienteTelefono::create($dataTelefono);
                    if (!$clienteTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El cliente no se ha creado',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cliente se ha creado correctamente',
                'cliente' => $cliente,
            ]);

        } catch (\Exception $e) {
            //Storage::deleteDirectory('clientes/'.$cliente->alias);
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function show(Cliente $cliente)
    {
        //
        if(is_null($cliente)){
            return response()->json([
                'ready' => false,
                'message' => 'Cliente no encontrado',
            ], 404);
        }else{

            $cliente->tipoDocumento;
            $cliente->emails = $cliente->emails()->get()->map(function($email){
                $email->tipoEmail->atributo;
                return $email;
            });
            $cliente->telefonos = $cliente->telefonos()->get()->map(function($telefono){
                $telefono->tipoTelefono->atributo;
                return $telefono;
            });
            $cliente->users;
            $cliente->envios;
            $cliente->reportes;

            return response()->json([
                'ready' => true,
                'cliente' => $cliente,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function edit(Cliente $cliente)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Cliente $cliente)
    {
        //
        try {
            DB::beginTransaction();

            $messages = [
                'nombreComercial.required' => 'El Nombre comercial es obligatorio.',
                'idTipoDocumento.required' => 'El Tipo de Documento es obligatorio.',
                'idTipoDocumento.exists' => 'Seleccione un Tipo de Documento valido.',
                'nroDocumento.required' => 'El Numero de Documento es obligatoria.',
                'nroDocumento.unique' => 'Ya se encuentra registrado un cliente con el mismo Tipo y Numero de Documento.',
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
                    Rule::unique('clientes')->ignore($cliente->id)->where(function ($query) use ($request){
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
            $cliente->nombreComercial = $request->nombreComercial;
            $cliente->idTipoDocumento = $request->idTipoDocumento;
            $cliente->nroDocumento = $request->nroDocumento;
            if (!$cliente->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha actualizado',
                ], 500);
            }

            // Datos Opcionales
            $cliente->razonSocial = isset($request->razonSocial) ? $request->razonSocial : null;
            $cliente->rubro = isset($request->rubro) ? $request->rubro : null;
            $cliente->observacion = isset($request->observacion) ? $request->observacion : null;
            if (!$cliente->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha actualizado',
                ], 500);
            }

            if ($request->hasFile('logo')) {

                $messagesLogo = [
                    'logo.file' => 'El logo debe ser un archivo.',
                    'logo.mimes' => 'Solo se permiten archivos de tipo .png,.jpg y .jpeg.',
                ];

                $validatorLogo = Validator::make($request->only('logo'), [
                    'logo' => ['file', 'mimes:png,jpg,jpeg'],
                ], $messagesLogo);

                if ($validatorLogo->fails()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorLogo->errors(),
                    ], 400);
                }

                if (!is_null($cliente->logo)) {
                    // Eliminar el logo actual
                    Storage::delete('clientes/'.$cliente->alias.'/'.$cliente->logo);
                }

                $logo = $request->file('logo');

                $extension = $logo->extension();
                $nameLogo = 'logo_' . Str::random(8). '.' . $extension;

                // Guardar el nuevo logo
                $logo->storeAs(
                    'clientes/'.$cliente->alias, $nameLogo
                );

                $cliente->logo = $nameLogo;
                $cliente->save();

            }

            if (isset($request->cliente_emails)) {

                // Eliminamos los registros actuales
                ClienteEmail::where('cliente_id', $cliente->id)->delete();

                $cliente_emails = json_decode($request->cliente_emails);

                foreach ($cliente_emails as $cliente_email) {
                    # code...

                    $messagesEmail = [
                        'email.unique' => 'El correo '.$cliente_email->email.' se encuentra duplicado.',
                        'email.email' => 'Ingrese un correo valido.',
                    ];
    
                    $validatorEmail = Validator::make(array('email' => $cliente_email->email), [
                        'email' => [
                            'email',
                            Rule::unique('cliente_emails')->where(function ($query) use ($cliente){
                                return $query->where('cliente_id', $cliente->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesEmail);

                    if ($validatorEmail->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorEmail->errors(),
                            'cliente_email' => $cliente_email,
                        ], 400);
                    }

                    $dataEmail = array(
                        'cliente_id' => $cliente->id,
                        'email' => $cliente_email->email,
                        'idTipoEmail' => $cliente_email->idTipoEmail,
                    );

                    $clienteEmail = ClienteEmail::create($dataEmail);
                    if (!$clienteEmail->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El cliente no se ha creado',
                        ], 500);
                    }
                }
            }

            if (isset($request->cliente_telefonos)) {

                // Eliminamos los registros actuales
                ClienteTelefono::where('cliente_id', $cliente->id)->delete();

                $cliente_telefonos = json_decode($request->cliente_telefonos);

                foreach ($cliente_telefonos as $cliente_telefono) {
                    # code...

                    $messagesTelefono = [
                        'telefono.unique' => 'El telefono '.$cliente_telefono->telefono.' se encuentra duplicado.',
                    ];
    
                    $validatorTelefono = Validator::make(array('telefono' => $cliente_telefono->telefono), [
                        'telefono' => [
                            Rule::unique('cliente_telefonos')->where(function ($query) use ($cliente){
                                return $query->where('cliente_id', $cliente->id)->whereNull('deleted_at');
                            }),
                        ],
                    ], $messagesTelefono);

                    if ($validatorTelefono->fails()) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'Los datos enviados no son correctos',
                            'errors' => $validatorTelefono->errors(),
                            'cliente_telefono' => $cliente_telefono,
                        ], 400);
                    }

                    $dataTelefono = array(
                        'cliente_id' => $cliente->id,
                        'telefono' => $cliente_telefono->telefono,
                        'idTipoTelefono' => $cliente_telefono->idTipoTelefono,
                    );

                    $clienteTelefono = ClienteTelefono::create($dataTelefono);
                    if (!$clienteTelefono->id) {
                        DB::rollBack();
                        return response()->json([
                            'ready' => false,
                            'message' => 'El cliente no se ha creado',
                        ], 500);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cliente se ha actualizado correctamente',
                'cliente' => $cliente,
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
     * @param  \App\Cliente  $cliente
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cliente $cliente)
    {
        //
        try {
            DB::beginTransaction();

            $existsCampaign = Campaign::where('cliente_id', $cliente->id)->exists();

            if($existsCampaign){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El cliente se encuentra relacionado con diferentes campañas.',
                ], 400);
            }

            ClienteEmail::where('cliente_id', $cliente->id)->delete();
            ClienteTelefono::where('cliente_id', $cliente->id)->delete();
            ClienteEncargado::where('cliente_id', $cliente->id)->delete();
            ClienteVocero::where('cliente_id', $cliente->id)->delete();
            ClienteEnvio::where('cliente_id', $cliente->id)->delete();

            // Eliminamos los mienbros del cliente
            User::where('cliente_id', $cliente->id)->delete();

            if (!$cliente->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El cliente no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El cliente se ha eliminado correctamente',
                'cliente' => $cliente,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function displayImage($id)
    {

        try {
          
          $cliente = Cliente::find($id);
    
          if(empty($cliente->logo)){
            $file = storage_path('app/clientes/') . 'logo_default.png';
          }elseif(!file_exists(storage_path('app/clientes/') . $cliente->alias . '/' . $cliente->logo)){
            $file = storage_path('app/clientes/') . 'logo_default.png';
          }else{
            $file = storage_path('app/clientes/') . $cliente->alias . '/' . $cliente->logo;
          }
    
        } catch (\Exception $e) {
          $file = storage_path('app/clientes/') . 'logo_default.png';
        }
        
        return response()->file($file);
       
    }

    public function addMember(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'email.required' => 'El correo es obligatorio.',
                'email.unique' => 'El correo ya se encuentra registrado.',
                'email.email' => 'Ingrese un correo valido.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => ['required'],
                'email' => ['required', 'unique:users,email,NULL,id,deleted_at,NULL', 'email'],
                'cliente_id' => ['required','exists:clientes,id'],
                'accessClipping' => ['required','boolean'],
                'sendAuto' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $data = array(
                'name' => $request->name,
                'email' => $request->email,
                'password' => '',
                'cliente_id' => $request->cliente_id,
                'accessClipping' => $request->accessClipping,
                'sendAuto' => $request->sendAuto,
            );

            $user = User::create($data);

            if (!$user->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El usuario no se ha asignado al cliente',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El usuario se ha asignado correctamente al cliente',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function editMember(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'email.required' => 'El correo es obligatorio.',
                'email.unique' => 'El correo ya se encuentra registrado.',
                'email.email' => 'Ingrese un correo valido.',
            ];

            $validator = Validator::make($request->all(), [
                'user_id' => ['required','exists:users,id'],
                'name' => ['required'],
                'email' => ['required', 'unique:users,email,' . $request->user_id . ',id,deleted_at,NULL', 'email'],
                'accessClipping' => ['required','boolean'],
                'sendAuto' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user = User::Find($request->user_id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->accessClipping = $request->accessClipping;
            $user->sendAuto = $request->sendAuto;
            if (!$user->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El usuario no se ha actualizado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El usuario se ha actualizado correctamente',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function deleteMember($idUser)
    {
        try {
            DB::beginTransaction();

            $user = User::Find($idUser);

            if (is_null($user)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            if (!$user->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El usuario no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El usuario se ha eliminado correctamente',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function enviarCredenciales($idUser)
    {
        try {
            DB::beginTransaction();

            $user = User::Find($idUser);

            if (is_null($user)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            $validatorEmail = Validator::make(array('email' => $user->email), [
                'email' => ['email'],
            ]);

            if ($validatorEmail->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Correo electronico no valido',
                ], 400);
            }else{
               
                $password = Str::random(8);
                $encriptado = bcrypt($password);
                $user->password = $encriptado;
                if (!$user->save()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Las credenciales no se han enviado',
                    ], 500);
                }

                try {

                    Mail::to($user->email,$user->name)
                        ->send(new ClienteCredencialesMail($user, $password));
      
                    DB::commit();
                    return response()->json([
                        'ready' => true,
                        'message' => 'Las credenciales se han enviado correctamente',
                    ]);
      
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Las credenciales no se han enviado',
                    ], 500);
                }

            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
