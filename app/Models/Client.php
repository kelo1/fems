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

    public function customerType(){
        return $this->hasOne('\App\CustomerType');
    }

    public function individualClient(){
        return $this->hasOne('\App\Individual_clients');
    }

    public function corporateClient(){
        return $this->hasOne('\App\Corporate_clients');
    }
}
