<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;


class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'service_provider_id',
        'client_id',
        'date_of_manufacturing',
        'expiry_date',
        'serial_number',
        'isActive',
        'created_by',
        'created_by_type',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->serial_number = 'EQU-' . strtoupper(uniqid());
        });
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function qrCode()
    {
        return $this->hasOne(QRCode::class, 'serial_number');
    }

    // Relationship with EquipmentClient
    public function equipmentClients()
    {
        return $this->hasMany(EquipmentClient::class, 'equipment_id');
    }

    // Relationship with EquipmentServiceProvider
    public function equipmentServiceProviders()
    {
        return $this->hasMany(EquipmentServiceProvider::class, 'equipment_id');
    }

    // Relationship with EquipmentActivity
    public function equipmentActivities()
    {
        return $this->hasMany(EquipmentActivity::class, 'equipment_id');
    }
}
