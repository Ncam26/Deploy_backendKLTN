<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingHistory extends Model
{
    protected $fillable = [
        'booking_id',
        'admin_id',
        'action',
        'reason',
        'old_court_id',
        'new_court_id',
        'old_booking_date',
        'new_booking_date',
        'old_start_time',
        'new_start_time',
        'old_end_time',
        'new_end_time',
        'old_status',
        'new_status',
    ];

    protected $casts = [
        'old_booking_date' => 'date:Y-m-d',
        'new_booking_date' => 'date:Y-m-d',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function admin()
    {
        return $this->belongsTo(
            User::class,
            'admin_id'
        );
    }
}