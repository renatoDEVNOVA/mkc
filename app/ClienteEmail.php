<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteEmail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id', 'email', 'idTipoEmail',
    ];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function tipoEmail() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoEmail');
    }
}
