<?php

use Illuminate\Database\Seeder;

use App\Atributo; 
use App\TipoAtributo; 

class TipoAtributoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $atributo = Atributo::where('slug', 'email')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Personal';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Trabajo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Otro';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'telephone')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Movil';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Fijo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Trabajo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Otro';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'address')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Casa';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Trabajo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Otro';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'social-media')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Facebook';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Instagram';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Twitter';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Linkedin';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Web';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'horario')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Programa';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Contacto';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Trabajo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'document')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'RUC';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'DNI';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'PASAPORTE';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'C. de E.';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'DNI 3 cuerpos';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $atributo = Atributo::where('slug', 'comunicacion')->first();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Email';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Fijo';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Celular';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Redes';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Interpersonal';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

        $tipoAtributo = new TipoAtributo();
        $tipoAtributo->name = 'Afiches';
        $tipoAtributo->atributo_id = $atributo->id;
        $tipoAtributo->save();

    }
}
