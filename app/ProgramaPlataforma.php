<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class ProgramaPlataforma extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'programa_id', 'idMedioPlataforma', 'valor',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'programaPlataforma';

    
    
    protected static $submitEmptyLogs = false;

    public function programa()
    {
        return $this->belongsTo('App\Programa');
    }

    public function medioPlataforma() 
    {
        return $this->belongsTo('App\MedioPlataforma', 'idMedioPlataforma');
    }
}
