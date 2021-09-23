<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class ClienteEncargado extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'cliente_id', 'idEncargado', 'tipoEncargado',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'clienteEncargado';

    protected static $submitEmptyLogs = false;

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function encargado() 
    {
        return $this->belongsTo('App\Persona', 'idEncargado');
    }
}
