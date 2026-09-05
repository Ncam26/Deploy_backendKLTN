<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'court_id',
        'booking_date',
        'start_time',
        'end_time',
        'total_price',
        'status',
        'payment_status',
        'payment_method',
        'payment_expires_at',
        'note',
        'action_reason',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'total_price' => 'integer',
        'payment_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function histories()
    {
        return $this->hasMany(BookingHistory::class);
    }
}
