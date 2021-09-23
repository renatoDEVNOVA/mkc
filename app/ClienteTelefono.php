<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteTelefono extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id', 'telefono', 'idTipoTelefono',
    ];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function tipoTelefono() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoTelefono');
    }
}
