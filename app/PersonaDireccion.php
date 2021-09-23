<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaDireccion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'direccion', 'ubigeo', 'idTipoDireccion',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function tipoDireccion() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoDireccion');
    }
}
