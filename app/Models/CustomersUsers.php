<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomersUsers extends Model
{
    use HasFactory;

    protected $table='customers_users';
    
    protected $fillable = ['name','email','password'];

    protected $hidden = [
        'password'
    ];
}
