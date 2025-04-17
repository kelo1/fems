<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProviderVAT extends Model
{
    use HasFactory;

    protected $table = 'service_provider_vats';

    protected $fillable = [
        'service_provider_id',
        'VAT_RATE',
        'created_by',
        'created_by_type',
    ];
}
