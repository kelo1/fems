<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;


class Client extends Model
{
    use HasFactory, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'client_type',
        'OTP',
        'email_token',
         'created_by',
        'created_by_type',
        'sms_verified',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];
    public function creator()
    {
        // Assuming you have a User model and a 'created_by' column in the clients table
        // that references the id of the user who created the client.
        
    return $this->belongsTo(User::class, 'created_by');

    }
    
    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function individualClient()
    {
        return $this->hasOne(Individual_clients::class, 'client_id');
    }

    public function corporateDetails()
    {
        return $this->hasOne(Corporate_clients::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(ServiceProvider::class, 'created_by');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    public function equipmentClients()
    {
        return $this->hasMany(EquipmentClient::class);
    }

    // Relationship with Invoicing
    public function invoicings()
    {
        return $this->hasMany(Invoicing::class, 'client_id');
    }
    
}
