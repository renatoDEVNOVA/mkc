<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CampaignGroup extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'nombre', 'descripcion', 'fechaInicio', 'fechaFin',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'campaignGroup';

    protected static $submitEmptyLogs = false;

}
