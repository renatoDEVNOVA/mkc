<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nicolaslopezj\Searchable\SearchableTrait;
use Spatie\Activitylog\Traits\LogsActivity;

class Campaign extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use SearchableTrait;

    protected $searchable = [
        'columns' => [
            'campaigns.titulo' => 10,
            'campaigns.observacion' => 10,
            'users.name'=>10,
            'campaigns.fechaInicio'=>10,
            'campaigns.fechaFin' => 10,
            'clientes.nombreComercial'=>10,
        ], 
        'joins' => [
            'clientes' => ['campaigns.cliente_id','clientes.id'],
            'campaign_responsables' => ['campaign_responsables.campaign_id','campaigns.id'],
            'users' => ['users.id','campaign_responsables.user_id'],
        ],
        'groupBy'=>'campaigns.id'
    ];

   
    
    protected $fillable = [
        'titulo',
        'alias',
        'fechaInicio',
        'fechaFin',
        'idCampaignGroup',
        'cliente_id',
        'idAgente',
        'observacion',
        'tipoPublico',
        'tipoObjetivo',
        'tipoAudiencia',
        'interesPublico',
        'novedad',
        'actualidad',
        'autoridadCliente',
        'mediaticoCliente',
        'autoridadVoceros',
        'mediaticoVoceros',
        'pesoPublico',
        'pesoObjetivo',
        'pesoAudiencia',
        'pesoInteresPublico',
        'pesoNovedad',
        'pesoActualidad',
        'pesoAutoridadCliente',
        'pesoMediaticoCliente',
        'pesoAutoridadVoceros',
        'pesoMediaticoVoceros',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'campaign';

    protected static $submitEmptyLogs = false;

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function campaignGroup() 
    {
        return $this->belongsTo('App\CampaignGroup', 'idCampaignGroup');
    }

    public function agente() 
    {
        return $this->belongsTo('App\User', 'idAgente');
    }

    public function etiquetas()
    {
        return $this->belongsToMany('App\Etiqueta')
                    ->whereNull('campaign_etiqueta.deleted_at')
                    ->withTimestamps();
    }

    public function campaignPlataformas()
    {
        return $this->hasMany('App\CampaignPlataforma');
    }

    public function campaignVoceros()
    {
        return $this->hasMany('App\CampaignVocero');
    }

    public function campaignResponsables()
    {
        return $this->hasMany('App\CampaignResponsable');
    }

    public function planMedios()
    {
        return $this->hasMany('App\PlanMedio');
    }
}
