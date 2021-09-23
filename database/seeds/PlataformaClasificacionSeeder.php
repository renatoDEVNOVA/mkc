<?php

use Illuminate\Database\Seeder;

use App\Plataforma; 
use App\PlataformaClasificacion; 

class PlataformaClasificacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $plataforma = Plataforma::where('descripcion', 'Web')->first();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'URL';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataforma = Plataforma::where('descripcion', 'Television')->first();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Señal Abierta';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Movistar TV';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataforma = Plataforma::where('descripcion', 'Radio')->first();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Señal Abierta';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataforma = Plataforma::where('descripcion', 'Impreso')->first();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Revista';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataforma = Plataforma::where('descripcion', 'Social Media')->first();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Facebook';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();

        $plataformaClasificacion = new PlataformaClasificacion();
        $plataformaClasificacion->descripcion = 'Instagram';
        $plataformaClasificacion->plataforma_id = $plataforma->id;
        $plataformaClasificacion->save();
    }
}
