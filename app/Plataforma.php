<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Plataforma extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'descripcion',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'plataforma';

    protected static $submitEmptyLogs = false;

    public function clasificacions()
    {
        return $this->hasMany('App\PlataformaClasificacion');
    }
}
