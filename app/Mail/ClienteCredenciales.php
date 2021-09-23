<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClienteCredenciales extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $password;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($usuario, $password)
    {
        //
        $this->usuario = $usuario;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.cliente-credenciales')
                    ->subject('¡'.$this->usuario->name.', bienvenido al Clipping de ADP!');
    }
}
