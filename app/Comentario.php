<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Comentario extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'bitacora_id', 'contenido', 'user_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'comentario';

    protected static $submitEmptyLogs = false;

    public function bitacora() 
    {
        return $this->belongsTo('App\Bitacora');
    }

    public function user() 
    {
        return $this->belongsTo('App\User');
    }
}
