<?php

use Illuminate\Database\Seeder;

use App\Plataforma;

class PlataformaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $plataforma = new Plataforma();
        $plataforma->descripcion = 'Web';
        $plataforma->save();

        $plataforma = new Plataforma();
        $plataforma->descripcion = 'Television';
        $plataforma->save();

        $plataforma = new Plataforma();
        $plataforma->descripcion = 'Radio';
        $plataforma->save();

        $plataforma = new Plataforma();
        $plataforma->descripcion = 'Impreso';
        $plataforma->save();

        $plataforma = new Plataforma();
        $plataforma->descripcion = 'Social Media';
        $plataforma->save();
        
    }
}
