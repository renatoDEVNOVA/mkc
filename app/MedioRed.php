<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedioRed extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medio_id', 'red', 'idTipoRed',
    ];

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function tipoRed() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoRed');
    }
}
