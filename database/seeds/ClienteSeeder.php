<?php

use Illuminate\Database\Seeder;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $clientes = factory(App\Cliente::class,3)->create()->each(function ($cliente) {
            $cliente->alias = $cliente->id . '_' . Str::random(8);
            $cliente->save();
            
            Storage::makeDirectory('clientes/'.$cliente->alias);
        });
    }
}
