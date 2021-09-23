<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'nombreComercial', 'razonSocial', 'propietario', 'representanteLegal', 'idTipoDocumento', 'nroDocumento',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'company';

    protected static $submitEmptyLogs = false;

    public function tipoDocumento() 
    {

        return $this->belongsTo('App\TipoAtributo', 'idTipoDocumento');
    }

    public function medios()
    {
        return $this->hasMany('App\Medio');
    }
}
