<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reporte extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'nameReporte', 'createdDate', 'cliente_id', 'tipoFrecuencia', 'numPeriodo', 'year', 'tipoReporte',
    ];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }
}
