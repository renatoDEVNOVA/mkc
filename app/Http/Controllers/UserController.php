<?php

namespace App\Http\Controllers;

use App\User;
use App\Models\Tenant\User as UserTenant;
use Illuminate\Http\Request;

use App\Campaign;
use App\Bitacora;
use App\TipoCambio;
use Illuminate\Support\Str;
use Validator;
use DB;
use Storage;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Controller
{
    public $user;

    public function __construct(User $user, UserTenant $userTenat)
    {
        //$this->middleware('jwt.auth', ['except' => ['login']]);
        $this->user = $user;
        if (config('auth.defaults.guard')==="tenant") {
            $this->user = $userTenat;
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        //$users = $this->user::with('roles')->whereNull('cliente_id')->get();
        $users = $this->user::get();
        return response()->json([
            'ready' => true,
            'users' => $users,
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

    public function storeUserTenant(Request $request)
    {
        $user = $this->user;
        $user->name = $request->name;
        $user->email = bcrypt($request->password);
        $user->email = $request->email;
        $user->save();
        return response()->json([
            "ready"=>true,
            "user"=>$user
            ]
        );
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'password.required' => 'La contraseña es obligatoria.',
                'email.required' => 'El correo es obligatorio.',
                'email.unique' => 'El correo ya se encuentra registrado.',
                'email.email' => 'Ingrese un correo valido.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => ['required'],
                'email' => ['required', 'unique:users,email,NULL,id,deleted_at,NULL', 'email'],
                'password' => ['required'],
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
                'password' => bcrypt($request->password), // Encriptar contraseña
            );

            //$user = $this->user::create($request->all());
            $user = $this->user::create($data);

            if (!$user->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El usuario no se ha creado',
                ], 500);
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

                $photo = $request->file('photo');

                $extension = $photo->extension();
                $namePhoto = $user->id . '_' . Str::random(8). '.' . $extension;

                // Guardar foto
                //$photo->move(storage_path('app/users'),  $namePhoto);
                $photo->storeAs(
                    'users', $namePhoto
                );

                $user->photo = $namePhoto;
                $user->save();

            }

            if (isset($request->roles)) {

                $rolesDecode = json_decode($request->roles);

                $user->roles()->sync($rolesDecode);

                /*foreach ($request->roles as $roleId) {
                    # code...
                    $user->roles()->attach($roleId);
                }*/
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El usuario se ha creado correctamente',
                'user' => $user,
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $user = $this->user::find($id);

        if(is_null($user)){
            return response()->json([
                'ready' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }else{

            $user->roles = $user->roles()->get()->map(function($role){
                return $role->id;
            });;

            return response()->json([
                'ready' => true,
                'user' => $user,
            ]);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
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
                'email' => ['required', 'unique:users,email,' . $user->id . ',id,deleted_at,NULL', 'email'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            //$user->update($request->all());
            $user->name = $request->name;
            $user->email = $request->email;
            if (!$user->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El usuario no se ha actualizado',
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

            if (isset($request->roles)) {

                $rolesDecode = json_decode($request->roles);

                $user->roles()->sync($rolesDecode);

                /*$user->roles()->detach();

                foreach ($request->roles as $roleId) {
                    # code...
                    $user->roles()->attach($roleId);
                }*/
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();

            $existsCampaign = Campaign::where('idAgente', $user->id)->exists();

            if($existsCampaign){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El usuario se encuentra relacionado con diferentes campañas.',
                ], 400);
            }

            $existsBitacora = Bitacora::where('user_id', $user->id)->exists();

            if($existsBitacora){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El usuario se encuentra relacionado con diferentes bitacoras.',
                ], 400);
            }

            $existsTipoCambio = TipoCambio::where('user_id', $user->id)->exists();

            if($existsTipoCambio){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El usuario se encuentra relacionado con diferentes tipos de cambio.',
                ], 400);
            }

            $user->roles()->detach();

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

    public function displayImage($id)
    {

        try {
          
          $user = $this->user::find($id);
    
          if(empty($user->photo)){
            $file = storage_path('app/users/') . 'default.png';
          }elseif(!file_exists(storage_path('app/users/') . $user->photo)){
            $file = storage_path('app/users/') . 'default.png';
          }else{
            $file = storage_path('app/users/') . $user->photo;
          }
    
        } catch (\Exception $e) {
          $file = storage_path('app/users/') . 'default.png';
        }
        
        return response()->file($file);
       
    }

    public function getCount()
    {

        $count = $this->user::whereNull('cliente_id')->count();

        return response()->json([
            'ready' => true,
            'count' => $count,
        ]);
    }

    public function getListByRole($idRole)
    {
        $users = $this->user::whereNull('cliente_id')->whereHas('roles', function (Builder $query) use ($idRole){
            $query->where('roles.id', $idRole);
        })->get();

        return response()->json([
            'ready' => true,
            'users' => $users->values(),
        ]);
    }
}
