<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaRed extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'red', 'idTipoRed',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function tipoRed() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoRed');
    }
}
