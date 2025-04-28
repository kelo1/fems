<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QRCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'qr_code_path',
    ];

    protected $table = 'qrcodes';

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'serial_number');
    }
}
