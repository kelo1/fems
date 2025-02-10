<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Corporate_clients extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'ghanapost_gps',
        'certificate_of_incorporation',
        'company_registration',
        'client_id',
    ];

    public function client(){
        return $this->belongsTo('\App\Client');
    }
}
