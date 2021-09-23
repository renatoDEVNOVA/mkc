<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteEnvio extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'cliente_id', 'cron', 'tiposReporte', 'tipoFrecuencia', 'periodoActual',
    ];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }
}
