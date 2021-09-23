<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class DetallePlanMedio extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'idPlanMedio',
        'idProgramaContacto',
        'idsMedioPlataforma',
        'tipoTier',
        'tipoNota',
        'tipoEtapa',
        'muestrasRegistradas',
        'muestrasEnviadas',
        'muestrasVerificadas',
        'statusPublicado',
        'statusExperto',
        'vinculado',
        'idDetallePlanMedioPadre',
        'user_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'detallePlanMedio';

    protected static $submitEmptyLogs = false;

    public function planMedio()
    {
        return $this->belongsTo('App\PlanMedio', 'idPlanMedio');
    }

    public function programaContacto() 
    {
        return $this->belongsTo('App\ProgramaContacto', 'idProgramaContacto');
    }

    public function detallePlanMedioPadre() 
    {
        return $this->belongsTo('App\DetallePlanMedio', 'idDetallePlanMedioPadre');
    }

    public function user() 
    {
        return $this->belongsTo('App\User');
    }

    public function voceros()
    {
        return $this->belongsToMany('App\Persona', 'detalle_plan_medio_vocero', 'idDetallePlanMedio', 'idVocero')
                    ->whereNull('detalle_plan_medio_vocero.deleted_at')
                    ->withTimestamps();
    }

    public function bitacoras()
    {
        return $this->hasMany('App\Bitacora', 'idDetallePlanMedio');
    }

    public function resultados()
    {
        return $this->belongsToMany('App\ResultadoPlataforma', 'detalle_plan_resultado_plataformas', 'idDetallePlanMedio', 'idResultadoPlataforma')
                    ->whereNull('detalle_plan_resultado_plataformas.deleted_at')
                    ->withTimestamps();
    }
}
