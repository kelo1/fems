<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientHistory extends Model
{
    use HasFactory;

    protected $table = 'client_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id',
        'old_service_provider_id',
        'new_service_provider_id',
    ];

    /**
     * Define the relationship with the Client model.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Define the relationship with the old ServiceProvider model.
     */
    public function oldServiceProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'old_service_provider_id');
    }

    /**
     * Define the relationship with the new ServiceProvider model.
     */
    public function newServiceProvider()
    {
        return $this->belongsTo(ServiceProvider::class, 'new_service_provider_id');
    }
}
