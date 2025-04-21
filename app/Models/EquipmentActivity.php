<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity',
        'next_maintenance_date',
        'service_provider_id',
        'client_id',
        'equipment_id',
        'device_serial_number',
        'created_by',
        'created_by_type',
    ];

    // Relationship with the Client model
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    // Relationship with the ServiceProvider model
    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    // Relationship with the Equipment model
    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    // Relationship with the ServiceProviderDevice model
    public function serviceProviderDevice()
    {
        return $this->belongsTo(ServiceProviderDevice::class, 'device_serial_number', 'device_serial_number');
    }
}
