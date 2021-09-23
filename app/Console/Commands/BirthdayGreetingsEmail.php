<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Persona;
use App\PersonaEmail;
use Mail;
use Log;
use App\Mail\BirthdayGreetings as BirthdayGreetingsMail;

class BirthdayGreetingsEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:birthday_greetings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un correo de felicitaciones de cumpleaños a las personas registradas en el sistema';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('ENVIO DE FELICITACIONES DE CUMPLEAÑOS');

        $diaActual = date("d");
        $mesActual = date("m");

        Log::info('Fecha: '.$diaActual.'-'.$mesActual);

        $personas = Persona::all();

        foreach ($personas as $persona) {
            # code...
            $diaNac = date("d",strtotime($persona->fechaNacimiento));
            $mesNac = date("m",strtotime($persona->fechaNacimiento));

            if(($diaActual == $diaNac) && ($mesActual == $mesNac)){

                Log::info($persona->nombres.' '.$persona->apellidos);

                $persona_emails = PersonaEmail::where('persona_id', $persona->id)->get();

                foreach ($persona_emails as $persona_email) {
                    # code...
                    Log::info($persona_email->email);

                    Mail::to($persona_email->email)
                        ->send(new BirthdayGreetingsMail($persona));
                }
                
            }

        }


        return 0;
    }
}
