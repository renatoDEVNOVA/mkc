<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildrenItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'state', 'name', 'type', 'menu_id',
    ];

    public function menu()
    {
        return $this->belongsTo('App\Menu');
    }
}
