<?php

use Illuminate\Database\Seeder;

use App\Role;
use App\Permission;  

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $role = Role::find(1);

        $permissions = Permission::all();
        foreach ($permissions as $permission) {
            # code...
            $role->permissions()->attach($permission->id);
        }

    }
}
