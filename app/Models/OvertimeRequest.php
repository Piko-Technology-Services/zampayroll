<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    public const STATUSES = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public const TYPES = [
        'normal' => [
            'label'      => 'Normal Overtime',
            'tooltip'    => 'Working day',
            'multiplier' => 1.5,
        ],
        'double' => [
            'label'      => 'Double Overtime',
            'tooltip'    => 'Holiday or non-working day',
            'multiplier' => 2.0,
        ],
    ];

    protected $fillable = [
        'employee_id',
        'company_email',
        'date',
        'start_time',
        'end_time',
        'hours',
        'type',
        'rate_multiplier',
        'daily_rate',
        'amount',
        'comment',
        'status',
        'hr_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'date'         => 'date',
        'hours'        => 'decimal:2',
        'rate_multiplier' => 'decimal:2',
        'daily_rate'   => 'decimal:2',
        'amount'       => 'decimal:2',
        'reviewed_at'  => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
