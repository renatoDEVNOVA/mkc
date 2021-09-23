<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class ClienteVocero extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'cliente_id', 'idVocero', 'cargo',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'clienteVocero';

    protected static $submitEmptyLogs = false;

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function vocero()
    {
        return $this->belongsTo('App\Persona', 'idVocero');
    }
}
