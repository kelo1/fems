<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;


class FEMSAdmin extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

     // Explicitly specify the table name
    protected $table = 'fems_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function createdBy()
    {
        return $this->hasMany(CertificateType::class, 'created_by');
    }
    public function createdByType()
    {
        return $this->hasMany(CertificateType::class, 'created_by_type');
    }
}
