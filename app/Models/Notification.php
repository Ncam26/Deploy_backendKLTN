<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'booking_id',
        'title',
        'message',
        'send_at',
        'status',
    ];

    protected $casts = [
        'send_at' => 'datetime',
    ];
}
