<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    public const STATUSES = [
        'pending'   => 'Waiting',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    /** Starting rates (companies change them in Overtime → Rates & rules). */
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
        'break_minutes',
        'hourly_rate',
        'rate_base',
        'requested_hours',
        'recorded_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'payroll_run_id',
    ];

    protected $casts = [
        'date'         => 'date',
        'hours'        => 'decimal:2',
        'rate_multiplier' => 'decimal:2',
        'daily_rate'   => 'decimal:2',
        'amount'       => 'decimal:2',
        'reviewed_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'hourly_rate'  => 'decimal:2',
        'requested_hours' => 'decimal:2',
        'break_minutes' => 'integer',
    ];

    public function typeLabel(): string
    {
        $t = app(\App\Services\Overtime\OvertimeService::class)->types($this->company_id)[$this->type] ?? null;

        return $t['label'] ?? self::TYPES[$this->type]['label'] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Paid in a payroll that is approved or paid — can no longer change. */
    public function isLockedInPayroll(): bool
    {
        return $this->payroll_run_id && ($run = PayrollRun::query()->find($this->payroll_run_id)) && $run->isLocked();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
