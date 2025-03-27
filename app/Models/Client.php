<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Client extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'client_type',
        'OTP',
        'email_token',
    ];

    protected $hidden = [
        'password',
    ];

    public function customerType()
    {
        return $this->hasOne(CustomerType::class);
    }

    public function individualClient()
    {
        return $this->hasOne(Individual_clients::class, 'client_id');
    }

    public function corporateClient()
    {
        return $this->hasOne(Corporate_clients::class, 'client_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    
}
