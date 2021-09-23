<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Tema extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'descripcion',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'tema';

    protected static $submitEmptyLogs = false;

    public function personas()
    {
        return $this->belongsToMany('App\Persona')
                    ->whereNull('persona_tema.deleted_at')
                    ->withTimestamps();
    }

}
