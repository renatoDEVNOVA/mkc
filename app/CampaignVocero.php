<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CampaignVocero extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'campaign_id', 'idVocero', 'vinculo', 'observacion',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'campaignVocero';

    protected static $submitEmptyLogs = false;

    public function campaign()
    {
        return $this->belongsTo('App\Campaign');
    }

    public function vocero()
    {
        return $this->belongsTo('App\Persona', 'idVocero');
    }
}
