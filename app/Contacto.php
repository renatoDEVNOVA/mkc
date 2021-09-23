<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contacto extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function programaContactos()
    {
        return $this->hasMany('App\ProgramaContacto');
    }
}
