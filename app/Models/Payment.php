<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'order_id',
        'request_id',
        'amount',
        'status',
        'trans_id',
        'pay_url',
        'result_code',
        'message',
        'response_data',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'result_code' => 'integer',
        'response_data' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
