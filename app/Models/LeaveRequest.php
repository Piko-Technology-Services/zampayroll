<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    public const STATUSES = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

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
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'return_date' => 'date',
        'days'        => 'decimal:2',
        'documents'   => 'array',
        'reviewed_at' => 'datetime',
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
