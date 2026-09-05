<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairTicket extends Model
{
    protected $fillable = [
        'equipment_id',
        'court_id',
        'reason',
        'description',
        'start_date',
        'expected_end_date',
        'technician',
        'estimated_cost',
        'actual_cost',
        'blocks_court',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'expected_end_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'estimated_cost' => 'integer',
        'actual_cost' => 'integer',
        'blocks_court' => 'boolean',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}