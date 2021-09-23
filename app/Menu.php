<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'state', 'name', 'type', 'icon',
    ];

    public function badgeItems()
    {
        return $this->hasMany('App\BadgeItem');
    }

    public function childrenItems()
    {
        return $this->hasMany('App\ChildrenItem');
    }
    
    public function roles()
    {
        return $this->belongsToMany('App\Role');
    }
}
