<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BadgeItem extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'type', 'value', 'menu_id',
    ];

    public function menu()
    {
        return $this->belongsTo('App\Menu');
    }
}
