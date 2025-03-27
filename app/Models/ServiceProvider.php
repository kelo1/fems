<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ServiceProvider extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'gps_address',
        'password',
        'license_id',
        'isActive',
    ];

    protected $hidden = [
        'password',
    ];

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }


    public function licenseType()
    {
        return $this->belongsTo(LicenseType::class, 'license_id');
    }
}
