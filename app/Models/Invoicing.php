<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoicing extends Model
{
    use HasFactory;

    protected $table = 'invoicings';

    protected $fillable = [
        'service_provider_id',
        'invoice_number',
        'equipment_serial_number',
        'client_id',
        'invoice_details',
        'invoice',
        'payment_amount',
        'created_by',
        'created_by_type',
        'payment_status',
    ];

     // These relationships assumes that the client_id in the Invoicing model corresponds to the id in the Client model
    // and that the client_id in the EquipmentClient model corresponds to the id in the Client model
    // and that the equipment_serial_number in the Invoicing model corresponds to the serial_number in the Equipment model
    // and that the equipment_serial_number in the EquipmentClient model corresponds to the serial_number in the Equipment model
    // and that the equipment_serial_number in the Equipment model corresponds to the serial_number in the EquipmentClient model

    // Relationship with ServiceProvider
    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    // Relationship with Client

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_serial_number', 'serial_number');
    }
   

}
