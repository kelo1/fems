<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class ServiceProvider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'gps_address',
        'password',
        'license_id',
        'isActive',
        'email_token',
        'OTP',
       'sms_verified',
       'email_verified_at',
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

    public function equipmentServiceProviders()
    {
        return $this->hasMany(EquipmentServiceProvider::class);
    }

    // Invoice relationship
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'service_provider_id');
    }
}
