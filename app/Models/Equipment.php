<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'code',
        'court_id',
        'category',
        'purchase_date',
        'note',
        'is_active',
        'status',

        /*
         * Các cột cũ được giữ lại để không làm
         * hỏng dữ liệu và giao diện hiện tại.
         */
        'repair_reason',
        'repair_description',
        'repair_start',
        'repair_end',
        'repair_cost',
        'technician',
        'blocks_court',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'blocks_court' => 'boolean',
        'repair_cost' => 'integer',
        'repair_start' => 'date:Y-m-d',
        'repair_end' => 'date:Y-m-d',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function repairTickets()
    {
        return $this->hasMany(
            RepairTicket::class
        );
    }

    public function currentRepair()
    {
        return $this->hasOne(
            RepairTicket::class
        )
            ->where('status', 'in_progress')
            ->latest('id');
    }
}