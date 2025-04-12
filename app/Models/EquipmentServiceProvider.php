<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentServiceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'serial_number',
        'service_provider_id',
        'status_service_provider',
    ];

    // Relationship with Equipment
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    // Relationship with ServiceProvider
    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
