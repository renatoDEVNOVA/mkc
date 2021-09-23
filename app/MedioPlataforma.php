<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class MedioPlataforma extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'medio_id', 'idPlataformaClasificacion', 'valor', 'alcance', 'observacion',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'medioPlataforma';

    protected static $submitEmptyLogs = false;

    public function medio()
    {
        return $this->belongsTo('App\Medio');
    }

    public function plataformaClasificacion() 
    {
        return $this->belongsTo('App\PlataformaClasificacion', 'idPlataformaClasificacion');
    }
}
