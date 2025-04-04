<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class GRA extends Authenticatable
{
    use HasApiTokens, HasFactory;

    // Explicitly specify the table name
    protected $table = 'gras';

    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'gps_address',
        'password',
        'isActive',
       
    ];

    protected $hidden = [
        'password',
    ];
}
