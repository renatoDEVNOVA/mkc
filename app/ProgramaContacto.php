<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class ProgramaContacto extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'programa_id', 'idContacto', 'tipoInfluencia', 'idsCargo', 'idsMedioPlataforma', 'observacion', 'activo',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'programaContacto';

    protected static $submitEmptyLogs = false;

    public function programa()
    {
        return $this->belongsTo('App\Programa');
    }

    public function contacto()
    {
        return $this->belongsTo('App\Persona', 'idContacto');
    }

    public function detallePlanMedios()
    {
        return $this->hasMany('App\DetallePlanMedio', 'idProgramaContacto');
    }
}
