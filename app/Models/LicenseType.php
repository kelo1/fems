<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function serviceProvider()
    {
        return $this->hasOne(ServiceProvider::class, 'license_id');
    }
}
