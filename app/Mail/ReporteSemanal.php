<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporteSemanal extends Mailable
{
    use Queueable, SerializesModels;

    public $tituloCorreo;
    public $destinatario;
    public $fileName;
    public $fechaInicio;
    public $fechaFin;
    public $asunto;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($tituloCorreo, $destinatario, $fileName, $fechaInicio, $fechaFin, $asunto)
    {
        //
        $this->tituloCorreo = $tituloCorreo;
        $this->destinatario = $destinatario;
        $this->fileName = $fileName;
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
        return $this->view('emails.reporte-semanal')
                    ->subject($this->asunto);
    }
}
