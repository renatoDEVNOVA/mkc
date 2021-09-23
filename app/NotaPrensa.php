<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class NotaPrensa extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'titulo', 'subtitulo', 'descripcion', 'observacion', 'fechaValidez', 'nombreArchivo',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'notaPrensa';

    protected static $submitEmptyLogs = false;

    public function planMedios()
    {
        return $this->hasMany('App\PlanMedio', 'idNotaPrensa');
    }
}
