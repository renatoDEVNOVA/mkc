<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atributo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description',
    ];

    public function tipoAtributos()
    {
        return $this->hasMany('App\TipoAtributo');
    }
    
}
