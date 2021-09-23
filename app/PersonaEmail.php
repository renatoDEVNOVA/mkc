<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaEmail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'email', 'idTipoEmail',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function tipoEmail() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoEmail');
    }
}
