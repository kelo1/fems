<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'serial_number',
        'client_id',
        'status_client',
    ];

    // Relationship with Equipment
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    // Relationship with Client
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
