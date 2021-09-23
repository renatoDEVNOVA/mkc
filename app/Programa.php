<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Programa extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'nombre', 'descripcion', 'periodicidad', 'medio_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'programa';

    protected static $submitEmptyLogs = false;

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function categorias()
    {
        return $this->belongsToMany('App\Categoria')
                    ->whereNull('categoria_programa.deleted_at')
                    ->withTimestamps();
    }

    public function programaPlataformas()
    {
        return $this->hasMany('App\ProgramaPlataforma');
    }

    public function programaContactos()
    {
        return $this->hasMany('App\ProgramaContacto');
    }
}
