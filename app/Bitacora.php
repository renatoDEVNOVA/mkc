<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Bitacora extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'idDetallePlanMedio', 'tipoBitacora', 'estado', 'observacion', 'idTipoComunicacion', 'user_id', 'idUserExtra',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'bitacora';

    protected static $submitEmptyLogs = false;

    public function detallePlanMedio()
    {
        return $this->belongsTo('App\DetallePlanMedio', 'idDetallePlanMedio');
    }

    public function tipoComunicacion() 
    {
        return $this->belongsTo('App\TipoAtributo', 'idTipoComunicacion');
    }

    public function user() 
    {
        return $this->belongsTo('App\User');
    }

    public function userExtra() 
    {
        return $this->belongsTo('App\User', 'idUserExtra');
    }

    

    /*public function comentarios()
    {
        return $this->hasMany('App\Comentario');
    }*/
}
