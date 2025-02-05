<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'client_type',
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
