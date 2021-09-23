<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Nicolaslopezj\Searchable\SearchableTrait;

class PlanMedio extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use SearchableTrait;

    protected $searchable = [
        'columns' => [
            'plan_medios.nombre' => 10,
            'plan_medios.descripcion' => 10,
            'campaigns.titulo' => 10,
            'clientes.nombreComercial' => 10,

        ],
        'joins' => [
            'campaigns' => ['plan_medios.campaign_id','campaigns.id'],
            'clientes' => ['campaigns.cliente_id','clientes.id'],
        ],
    ];

    protected $fillable = [
        'campaign_id', 'nombre', 'idNotaPrensa', 'descripcion', 'status', 'user_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'planMedio';

    protected static $submitEmptyLogs = false;

    public function campaign()
    {
        return $this->belongsTo('App\Campaign');
    }

    public function notaPrensa() 
    {
        return $this->belongsTo('App\NotaPrensa', 'idNotaPrensa');
    }

    public function detallePlanMedios()
    {
        return $this->hasMany('App\DetallePlanMedio', 'idPlanMedio');
    }

    public function user() 
    {
        return $this->belongsTo('App\User');
    }

    public function registros()
    {
        return $this->hasMany('App\Registro', 'idPlanMedio');
    }
}
