<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    use HasFactory;

    protected $table = 'billings';

    protected $fillable = [
        'DESCRIPTION',
        'VAT_APPLICABLE',
        'isACTIVE',
        'created_by',
        'created_by_type',
    ];
}
