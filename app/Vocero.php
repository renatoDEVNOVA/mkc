<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vocero extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'persona_id', 'famoso',
    ];

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }

    public function clienteVoceros()
    {
        return $this->hasMany('App\ClienteVocero');
    }

    public function campaignVoceros()
    {
        return $this->hasMany('App\CampaignVocero');
    }
}
