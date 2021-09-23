<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultadoPlataforma extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'idProgramaContacto', 'idMedioPlataforma', 'foto', 'fechaPublicacion', 'url', 'idTipoCambio', 'segundos', 'ancho', 'alto', 'cm2',
    ];

    public function programaContacto() 
    {
        return $this->belongsTo('App\ProgramaContacto', 'idProgramaContacto');
    }

    public function medioPlataforma() 
    {
        return $this->belongsTo('App\MedioPlataforma', 'idMedioPlataforma');
    }

    public function tipoCambio() 
    {
        return $this->belongsTo('App\TipoCambio', 'idTipoCambio');
    }

    public function detallePlanMedios()
    {
        return $this->belongsToMany('App\DetallePlanMedio', 'detalle_plan_resultado_plataformas', 'idResultadoPlataforma', 'idDetallePlanMedio')
                    ->whereNull('detalle_plan_resultado_plataformas.deleted_at')
                    ->withTimestamps();
    }
}
