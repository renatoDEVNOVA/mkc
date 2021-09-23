<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaHorario extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'idTipoHorario', 'descripcion', 'periodicidad', 'horaInicio', 'horaFin',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function tipoHorario() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoHorario');
    }
}
