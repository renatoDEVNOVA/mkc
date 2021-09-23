<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporteQuincenal extends Mailable
{
    use Queueable, SerializesModels;

    public $tituloCorreo;
    public $destinatario;
    public $fileName;
    public $Mes;
    public $fechaInicio;
    public $fechaFin;
    public $asunto;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($tituloCorreo, $destinatario, $fileName, $Mes, $fechaInicio, $fechaFin, $asunto)
    {
        //
        $this->tituloCorreo = $tituloCorreo;
        $this->destinatario = $destinatario;
        $this->fileName = $fileName;
        $this->Mes = $Mes;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->asunto = $asunto;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.reporte-quincenal')
                    ->subject($this->asunto);
    }
}
