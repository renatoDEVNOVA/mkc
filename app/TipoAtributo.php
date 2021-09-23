<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoAtributo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'atributo_id',
    ];

    public function atributo()
    {
        return $this->belongsTo('App\Atributo');
    }
}
