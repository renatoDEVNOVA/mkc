<?php

use Illuminate\Database\Seeder;

use App\Atributo; 

class AtributoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $atributo = new Atributo();
        $atributo->name = 'Correo';
        $atributo->slug = 'email';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Teléfono';
        $atributo->slug = 'telephone';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Dirección';
        $atributo->slug = 'address';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Red Social';
        $atributo->slug = 'social-media';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Horario';
        $atributo->slug = 'horario';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Documento';
        $atributo->slug = 'document';
        $atributo->save();

        $atributo = new Atributo();
        $atributo->name = 'Comunicación';
        $atributo->slug = 'comunicacion';
        $atributo->save();

    }
}
