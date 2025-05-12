<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateType extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_name',
        'created_by',
        'created_by_type',
    ];

    public function fems_admin()
    {
        return $this->belongsTo(FEMSAdmin::class, 'created_by');
    }
}
