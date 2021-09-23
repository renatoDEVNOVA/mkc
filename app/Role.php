<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Role extends Model
{
    use SoftDeletes;
    use LogsActivity;
    
    protected $fillable = [
        'name', 'slug', 'description',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'role';

    protected static $submitEmptyLogs = false;

    public function users()
    {
        return $this->belongsToMany('App\User')->withTimestamps();
    }

    public function menus()
    {
        return $this->belongsToMany('App\Menu');
    }

    public function permissions()
    {
        return $this->belongsToMany('App\Permission')->withTimestamps();
    }
}
