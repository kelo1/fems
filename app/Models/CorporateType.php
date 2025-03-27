<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function corporateClients()
    {
        return $this->hasMany(Corporate_clients::class);
    }
}
