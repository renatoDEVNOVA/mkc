<?php

use Illuminate\Database\Seeder;

use App\User;
use App\Role;  

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $user = new User();
        $user->name = 'Usuario ADP';
        $user->email = 'agente@adp.com';
        $user->password = bcrypt('agente');
        $user->save();

        $role = Role::where('name', 'Rol ADP')->first();
        $user->roles()->attach($role->id);

    }
}
