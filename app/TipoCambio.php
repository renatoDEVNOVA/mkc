<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class TipoCambio extends Model
{
    use SoftDeletes;
    use LogsActivity;
    
    protected $fillable = [
        'TC', 'user_id',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'tipoCambio';

    protected static $submitEmptyLogs = false;

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
