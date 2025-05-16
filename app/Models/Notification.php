<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class Notification extends DatabaseNotification
{
    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'serial_number',
        'recipient_type',
        'recipient_id',
        'title',
        'message',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

       protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
