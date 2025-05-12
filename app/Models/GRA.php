<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;


class GRA extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, Notifiable;

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
        'email_token',
        'OTP',
       'sms_verified',
       'email_verified_at',
       
    ];

    protected $hidden = [
        'password',
    ];
}
