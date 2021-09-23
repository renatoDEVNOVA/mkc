<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Medio extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'nombre', 'alias', 'tipoRegion', 'company_id', 'filial', 'idMedioPadre', 'observacion',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'medio';

    protected static $submitEmptyLogs = false;

    public function company()
    {
        return $this->belongsTo('App\Company');
    }

    public function medioPadre() 
    {

        return $this->belongsTo('App\Medio', 'idMedioPadre');
    }

    public function emails()
    {
        return $this->hasMany('App\MedioEmail');
    }

    public function telefonos()
    {
        return $this->hasMany('App\MedioTelefono');
    }

    public function direccions()
    {
        return $this->hasMany('App\MedioDireccion');
    }

    public function reds()
    {
        return $this->hasMany('App\MedioRed');
    }

    public function medioPlataformas()
    {
        return $this->hasMany('App\MedioPlataforma');
    }

    public function programas()
    {
        return $this->hasMany('App\Programa');
    }
}
