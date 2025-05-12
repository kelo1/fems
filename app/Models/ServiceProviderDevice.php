<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProviderDevice extends Model
{
    use HasFactory;

    protected $fillable = ['device_serial_number','description', 'service_provider_id'];

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
