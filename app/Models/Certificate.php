<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Certificate extends Model
{
    use HasFactory,  SoftDeletes;

    protected $fillable = [
        'certificate_id',
        'client_id',
        'fsa_id',
        'isVerified',
        'certificate_upload',
        'serial_number',
        'issued_date',
        'expiry_date',
        'status',
        'created_by',
        'created_by_type',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->serial_number = 'CER-' . strtoupper(uniqid());
        });
    }

    public function certificateType()
    {
        return $this->belongsTo(CertificateType::class, 'certificate_id');
    }

    public function fireServiceAgent()
    {
        return $this->belongsTo(FireServiceAgent::class, 'fsa_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
