<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    protected $fillable = [
        'code',
        'name',
        'area',
        'price',
        'opening_time',
        'closing_time',
        'amenities',
        'rating',
        'image',
        'status',
        'maintenance_reason',
        'maintenance_description',
        'maintenance_start',
        'maintenance_end',
        'maintenance_source',
    ];

    protected $casts = [
        'price' => 'integer',
        'rating' => 'float',
        'amenities' => 'array',
        'maintenance_start' => 'date:Y-m-d',
        'maintenance_end' => 'date:Y-m-d',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }
}