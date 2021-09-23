<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class PlataformaClasificacion extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'descripcion', 'plataforma_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'plataformaClasificacion';

    protected static $submitEmptyLogs = false;

    public function plataforma()
    {
        return $this->belongsTo('App\Plataforma');
    }
}
