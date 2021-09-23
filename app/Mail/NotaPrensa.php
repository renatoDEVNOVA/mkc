<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotaPrensa extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $archivos;
    public $notaPrensa;
    public $mensaje;
    public $asunto;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($usuario, $archivos, $notaPrensa, $mensaje, $asunto)
    {
        //
        $this->usuario = $usuario;
        $this->archivos = $archivos;
        $this->notaPrensa = $notaPrensa;
        $this->mensaje = $mensaje;
        $this->asunto = $asunto;

    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->from($this->usuario['email'], $this->usuario['name'])
                    ->view('emails.nota-prensa')
                    ->subject($this->asunto)
                    ->attach(storage_path('app/notaPrensas/').$this->notaPrensa->nombreArchivo);

        foreach ($this->archivos as $archivo) {
            # code...
            $email->attach($archivo->getRealPath(), array(
                'as' => $archivo->getClientOriginalName(),   
                'mime' => $archivo->getMimeType())
            );
        }
                
        return $email;
    }
}
