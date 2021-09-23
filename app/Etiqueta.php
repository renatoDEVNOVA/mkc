<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Nicolaslopezj\Searchable\SearchableTrait;

class Etiqueta extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use SearchableTrait;

    protected $searchable = [
        'columns' => [
            'etiquetas.slug' => 10,
        ],
    ];

    protected $fillable = [
        'slug',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'etiqueta';

    protected static $submitEmptyLogs = false;

    public function campaigns()
    {
        return $this->belongsToMany('App\Campaign')
                    ->whereNull('campaign_etiqueta.deleted_at')
                    ->withTimestamps();
    }
}
