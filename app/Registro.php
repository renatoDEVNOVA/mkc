<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registro extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'idPlanMedio', 'status', 'observacion', 'user_id',
    ];

    public function planMedio()
    {
        return $this->belongsTo('App\PlanMedio', 'idPlanMedio');
    }

    public function user() 
    {
        return $this->belongsTo('App\User');
    }
}
