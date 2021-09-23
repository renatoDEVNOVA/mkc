<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Categoria extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'descripcion', 'idCategoriaPadre',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'categoria';

    protected static $submitEmptyLogs = false;

    protected $appends = ['full_descripcion'];

    public function categoriaPadre() 
    {

        return $this->belongsTo('App\Categoria', 'idCategoriaPadre');
    }

    public function getFullDescripcionAttribute()
    {
        $categoriaPadre = Categoria::find($this->idCategoriaPadre);

        if(is_null($categoriaPadre)){
            return $this->descripcion;
        }else{
            return "{$categoriaPadre->full_descripcion} >> {$this->descripcion}";
        }
    
    }

    public function programas()
    {
        return $this->belongsToMany('App\Programa')
                    ->whereNull('categoria_programa.deleted_at')
                    ->withTimestamps();
    }

    public function personas()
    {
        return $this->belongsToMany('App\Persona')
                    ->whereNull('categoria_persona.deleted_at')
                    ->withTimestamps();
    }
}
