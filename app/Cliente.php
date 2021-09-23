<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;

class Cliente extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use Searchable;

    public function searchableAs()
    {
        return 'clientes_index';
    }

    protected $fillable = [
        'nombreComercial', 'razonSocial', 'rubro', 'alias', 'idTipoDocumento', 'nroDocumento', 'observacion', 'logo',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'cliente';

    protected static $submitEmptyLogs = false;

    public function tipoDocumento() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoDocumento');
    }

    public function emails()
    {
        return $this->hasMany('App\ClienteEmail');
    }

    public function telefonos()
    {
        return $this->hasMany('App\ClienteTelefono');
    }

    public function campaigns()
    {
        return $this->hasMany('App\Campaign');
    }

    public function clienteEncargados()
    {
        return $this->hasMany('App\ClienteEncargado');
    }

    public function clienteVoceros()
    {
        return $this->hasMany('App\ClienteVocero');
    }

    public function users()
    {
        return $this->hasMany('App\User');
    }

    public function envios()
    {
        return $this->hasMany('App\ClienteEnvio');
    }

    public function reportes()
    {
        return $this->hasMany('App\Reporte');
    }
}
