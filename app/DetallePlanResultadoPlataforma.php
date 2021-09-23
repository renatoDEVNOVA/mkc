<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetallePlanResultadoPlataforma extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'idDetallePlanMedio', 'idResultadoPlataforma',
    ];

    public function detallePlanMedio() 
    {
        return $this->belongsTo('App\DetallePlanMedio', 'idDetallePlanMedio');
    }

    public function resultadoPlataforma() 
    {
        return $this->belongsTo('App\ResultadoPlataforma', 'idResultadoPlataforma');
    }
}
