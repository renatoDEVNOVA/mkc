<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ImpactosDestinatario extends Mailable
{
    use Queueable, SerializesModels;

    public $tituloCorreo;
    public $usuario;
    public $destinatario;
    public $mensaje;
    public $filename;
    public $asunto;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($tituloCorreo, $usuario, $destinatario, $mensaje, $filename, $asunto)
    {
        //
        $this->tituloCorreo = $tituloCorreo;
        $this->usuario = $usuario;
        $this->destinatario = $destinatario;
        $this->mensaje = $mensaje;
        $this->filename = $filename;
        $this->asunto = $asunto;

    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->usuario['email'], $this->usuario['name'])
                    ->view('emails.impactos-destinatario')
                    ->subject($this->asunto);
    }
}
