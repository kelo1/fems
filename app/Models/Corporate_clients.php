<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Corporate_clients extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'branch_name',
        'company_address',
        'company_email',
        'company_phone',
        'certificate_of_incorporation',
        'company_registration',
        'gps_address',
        'client_id',
        'corporate_type_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function corporateType()
    {
        return $this->belongsTo(CorporateType::class);
    }
}
