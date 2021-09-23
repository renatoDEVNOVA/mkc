<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedioDireccion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medio_id', 'direccion', 'idTipoDireccion',
    ];

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function tipoDireccion() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoDireccion');
    }
}
