<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedioEmail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medio_id', 'email', 'idTipoEmail',
    ];

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function tipoEmail() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoEmail');
    }
}
