<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaTelefono extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'telefono', 'idTipoTelefono',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function tipoTelefono() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoTelefono');
    }
}
