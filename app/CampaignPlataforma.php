<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CampaignPlataforma extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'campaign_id', 'plataforma_id', 'meta',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'campaignPlataforma';

    protected static $submitEmptyLogs = false;

    public function campaign()
    {
        return $this->belongsTo('App\Campaign');
    }

    public function plataforma()
    {
        return $this->belongsTo('App\Plataforma');
    }
}
