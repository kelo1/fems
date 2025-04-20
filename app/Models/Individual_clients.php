<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Individual_clients extends Model
{
    use HasFactory, SoftDeletes;

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

