<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicesbyFSA extends Model
{
    use HasFactory;

    protected $table = 'invoices_by_fsa';
    protected $fillable = [
        'fsa_id',
        'invoice_number',
        'client_id',
        'invoice_details',
        'invoice',
        'payment_amount',
        'created_by',
        'created_by_type',
        'payment_status',
    ];

     // Relationship with FSA Agent
     public function fireServiceAgent()
     {
         return $this->belongsTo(FireServiceAgent::class, 'fsa_id');
     }

     // Relationship with Client
     public function client()
    {
         return $this->belongsTo(Client::class, 'client_id');
    }
}
