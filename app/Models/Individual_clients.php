<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Individual_clients extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'address',
        'document_type',
        'document',
        'gps_address',
        'client_id',
    ];

    public function client(){
        return $this->belongsTo(Client::class, 'client_id');
    }
}

