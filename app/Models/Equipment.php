<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Equipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'service_provider_id',
        'client_id',
        'date_of_manufacturing',
        'expiry_date',
        'serial_number',
        'isActive',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->serial_number = (string) Str::uuid();
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
        return $this->hasOne(QRCode::class, 'serial_number', 'serial_number');
    }
}
