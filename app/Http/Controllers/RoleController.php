<?php

namespace App\Http\Controllers;

use App\Role;
use Illuminate\Http\Request;

use Validator;
use DB;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::all();

        return response()->json([
            'ready' => true,
            'roles' => $roles,
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
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'name.unique' => 'Ya se encuentra registrado un rol con el mismo nombre.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'unique:roles,name,NULL,id,deleted_at,NULL'],
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
            );

            //$user = User::create($request->all());
            $role = Role::create($data);

            if (!$role->id) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El rol no se ha creado',
                ], 500);
            }

            if (isset($request->description)) {
                $role->description = $request->description;
                $role->save();
            }

            if (isset($request->permissions)) {

                $role->permissions()->sync($request->permissions);

                /*foreach ($request->permissions as $permissionId) {
                    # code...
                    $role->permissions()->attach($permissionId);
                }*/
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El rol se ha creado correctamente',
                'role' => $role,
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
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        //$user = User::find($id);

        if(is_null($role)){
            return response()->json([
                'ready' => false,
                'message' => 'El rol no se pudo encontrar',
            ], 404);
        }else{

            $role->permissions = $role->permissions()->get()->map(function($permission){
                return $permission->id;
            });;

            return response()->json([
                'ready' => true,
                'role' => $role,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
    {
        try {
            DB::beginTransaction();

            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'name.unique' => 'Ya se encuentra registrado un rol con el mismo nombre.',
            ];

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'unique:roles,name,' . $role->id . ',id,deleted_at,NULL'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $role->name = $request->name;
            if (!$role->save()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El rol no se ha actualizado',
                ], 500);
            }

            if (isset($request->description)) {
                $role->description = $request->description;
                $role->save();
            }

            if (isset($request->permissions)) {

                $role->permissions()->sync($request->permissions);

                /*$role->permissions()->detach();

                foreach ($request->permissions as $permissionId) {
                    # code...
                    $role->permissions()->attach($permissionId);
                }*/
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El rol se ha actualizado correctamente',
                'role' => $role,
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
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {

        try {
            DB::beginTransaction();

            $countUsers = $role->users()->count();

            if($countUsers > 0){
                return response()->json([
                    'ready' => false,
                    'message' => 'Imposible de eliminar. El rol se encuentra relacionado con diferentes usuarios.',
                ], 400);
            }

            $role->permissions()->detach();

            if (!$role->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El rol se ha eliminado correctamente',
                'role' => $role,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }
}
