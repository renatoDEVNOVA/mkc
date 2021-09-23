<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedioTelefono extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medio_id', 'telefono', 'idTipoTelefono',
    ];

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function tipoTelefono() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoTelefono');
    }
}
