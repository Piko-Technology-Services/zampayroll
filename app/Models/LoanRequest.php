<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRequest extends Model
{
    public const STATUSES = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public const PAYMENT_PLANS = [
        'monthly'    => 'Every Month Deduction',
        'bi_monthly' => 'Every Two Months Deduction',
        'once_off'   => 'Once-off Deduction from Next Salary',
        'other'      => 'Other (specify)',
    ];

    protected $fillable = [
        'employee_id',
        'company_email',
        'amount',
        'payment_plan',
        'payment_plan_note',
        'reason',
        'documents',
        'status',
        'hr_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
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
