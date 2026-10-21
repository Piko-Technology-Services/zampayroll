<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    public const STATUSES = [
        'pending'          => 'Waiting',
        'manager_approved' => 'Manager approved',
        'approved'         => 'Approved',
        'rejected'         => 'Rejected',
        'cancelled'        => 'Cancelled',
    ];

    /** Still waiting for a decision. */
    public const OPEN = ['pending', 'manager_approved'];

    // Leave categories recognised under Zambia's Employment Code Act, 2019,
    // plus a couple of common non-statutory ones (study, unpaid, other).
    public const TYPES = [
        'annual'       => 'Annual Leave',
        'sick'         => 'Sick Leave',
        'maternity'    => 'Maternity Leave',
        'paternity'    => 'Paternity Leave',
        'compassionate'=> 'Compassionate / Bereavement Leave',
        'family'       => 'Family Responsibility Leave',
        'study'        => 'Study Leave',
        'unpaid'       => 'Unpaid Leave',
        'other'        => 'Other',
    ];

    protected $fillable = [
        'employee_id',
        'leave_type',
        'company_email',
        'start_date',
        'end_date',
        'return_date',
        'days',
        'reason',
        'supervisor_email',
        'documents',
        'status',
        'hr_comment',
        'reviewed_by',
        'reviewed_at',
        'stage',
        'manager_approved_by',
        'manager_approved_at',
        'manager_comment',
        'deducted_days',
        'cancelled_at',
        'cancelled_by',
        'cancelled_by_employee',
        'cancel_reason',
        'recorded_by',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'return_date' => 'date',
        'days'        => 'decimal:2',
        'documents'   => 'array',
        'reviewed_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deducted_days' => 'decimal:2',
        'cancelled_by_employee' => 'boolean',
    ];

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Name of the type, from the company's leave types when available. */
    public function typeName(): string
    {
        return LeaveType::query()->where('key', $this->leave_type)->value('name')
            ?? self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function managerApprovedBy()
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
