<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use Validator;
use DB;
use Storage;

use Google_Client;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    /*public function __construct()
    {
        $this->middleware('jwt.auth', ['except' => ['login']]);
    }*/

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {

        $messages = [
            'password.required' => 'La contraseña es obligatoria.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingrese un correo valido.',
        ];

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $credentials = request(['email', 'password']);

        try {
            if (! $token = auth()->attempt($credentials)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Credenciales no validas',
                ], 401);
            }
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'ready' => false,
                'message' => 'No se pudo generar token de conexion',
            ], 500);
        }

        $user = auth()->user();

        // Solo usuarios de tipo Agente (cliente_id = NULL)
        if (!is_null($user->cliente_id)) {
            return response()->json([
                'ready' => false,
                'message' => 'Credenciales no validas',
            ], 401);
        }

        $user->roles = User::find($user->id)->roles()->get()->makeHidden(['permissions']);
        $user->permissions = User::find($user->id)->permissions;

        return response()->json([
            'ready' => true,
            'message' => 'Conexion exitosa',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function loginWithGoogle(Request $request)
    {
        DB::beginTransaction();

        $messages = [
        ];

        $validator = Validator::make($request->all(), [
            //'email' => ['required', 'email'],
            'authCode' => ['required'],
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }

        //$user = User::where('email', $request->email)->first();

        /*if (is_null($user) || is_null($user->access_token)) {

            if (empty($request->authCode)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Falta el codigo de autenticacion para conectar con Google',
                ], 500);
            }

            $client = new Google_Client();
            $client->setAuthConfig(storage_path('app/') . 'credentials.json');
            $client->setAccessType('offline');

            $accessToken = $client->fetchAccessTokenWithAuthCode($request->authCode);
            $client->setAccessToken($accessToken);

            if (array_key_exists('error', $accessToken)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar conectar con Google',
                ], 500);
            }

            if(is_null($user)){

                $payload = $client->verifyIdToken();

                $data = array(
                    'name' => $payload->name,
                    'email' => $payload->email,
                    'password' => '',
                );
    
                $user = User::create($data);
                if (!$user->id) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Error al intentar crear el usuario',
                    ], 500);
                }
            }

            $user->access_token = json_encode($accessToken);
            if (!$user->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar actualizar el usuario',
                ], 500);
            }
        }*/


        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/') . 'credentials.json');
        $client->setAccessType('offline');

        $accessToken = $client->fetchAccessTokenWithAuthCode($request->authCode);
        $client->setAccessToken($accessToken);

        if (array_key_exists('error', $accessToken)) {
            return response()->json([
                'ready' => false,
                'message' => 'Error al intentar conectar con Google',
            ], 500);
        }

        $payload = $client->verifyIdToken();
        $user = User::where('email', $payload->email)->first();

        if(is_null($user)){

            $data = array(
                'name' => $payload->name,
                'email' => $payload->email,
                'password' => '',
            );

            $user = User::create($data);
            if (!$user->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Error al intentar crear el usuario',
                ], 500);
            }
        }

        $user->access_token = json_encode($accessToken);
        if (!$user->save()) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
                'message' => 'Error al intentar actualizar el usuario',
            ], 500);
        }

        try {
            if (! $token = auth()->login($user)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Credenciales no validas',
                ], 401);
            }
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
                'message' => 'No se pudo generar token de conexion',
            ], 500);
        }

        $user->roles = User::find($user->id)->roles()->get()->makeHidden(['permissions']);
        $user->permissions = User::find($user->id)->permissions;

        DB::commit();
        return response()->json([
            'ready' => true,
            'message' => 'Conexion exitosa',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function loginClipping(Request $request)
    {

        $messages = [
            'password.required' => 'La contraseña es obligatoria.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingrese un correo valido.',
        ];

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $credentials = request(['email', 'password']);

        try {
            if (! $token = auth()->attempt($credentials)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Credenciales no validas',
                ], 401);
            }
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'ready' => false,
                'message' => 'No se pudo generar token de conexion',
            ], 500);
        }

        $user = auth()->user();

        $user->roles = User::find($user->id)->roles()->get()->makeHidden(['permissions']);
        $user->permissions = User::find($user->id)->permissions;

        return response()->json([
            'ready' => true,
            'message' => 'Conexion exitosa',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            $user = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json([
                'ready' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $user->roles = User::find($user->id)->roles()->get()->makeHidden(['permissions']);
        $user->permissions = User::find($user->id)->permissions;

        return response()->json([
            'ready' => true,
            'user' => $user,
        ]);
    }

    public function refresh(Request $request)
    {
        try {
            $user = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json([
                'ready' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

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
                'email' => ['required', 'unique:users,email,' . $user->id . ',id,deleted_at,NULL', 'email'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user->name = $request->name;
            $user->email = $request->email;
            if (!$user->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'Tu perfil no se ha actualizado',
                ], 500);
            }

            if (!empty($request->password)) {
                $encriptado = bcrypt($request->password); // Encriptar variable
                $user->password = $encriptado;
                $user->save();
            }

            if ($request->hasFile('photo')) {

                $messagesPhoto = [
                    'photo.file' => 'La foto debe ser un archivo.',
                    'photo.mimes' => 'Solo se permiten archivos de tipo .png,.jpg y .jpeg.',
                ];

                $validatorPhoto = Validator::make($request->only('photo'), [
                    'photo' => ['file', 'mimes:png,jpg,jpeg'],
                ], $messagesPhoto);

                if ($validatorPhoto->fails()) {
                    DB::rollBack();
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorPhoto->errors(),
                    ], 400);
                }

                if (!is_null($user->photo)) {
                    // Eliminar la foto actual
                    Storage::delete('users/'.$user->photo);
                }

                $photo = $request->file('photo');

                $extension = $photo->extension();
                $namePhoto = $user->id . '_' . Str::random(8). '.' . $extension;

                // Guardar la nueva foto
                //$photo->move(storage_path('app/users'),  $namePhoto);
                $photo->storeAs(
                    'users', $namePhoto
                );

                $user->photo = $namePhoto;
                $user->save();

            }

            $user->roles = User::find($user->id)->roles()->get()->makeHidden(['permissions']);
            $user->permissions = User::find($user->id)->permissions;

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'Tu perfil se ha actualizado correctamente',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
