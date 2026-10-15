<?php

namespace App\Models;

use App\Models\Concerns\HasInstallmentSchedule;
use Illuminate\Database\Eloquent\Model;

class LoanRequest extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    use HasInstallmentSchedule;

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
        'installment_amount',
        'reason',
        'documents',
        'status',
        'hr_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'installment_amount'  => 'decimal:2',
        'documents'           => 'array',
        'reviewed_at'         => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The ledger entry created from this application, if HR has added
     * one. Used to filter the "prefill from approved application"
     * picker on the Add Loan form so an already-ledgered application
     * doesn't show up again, and to show an "In Ledger" flag on the
     * applications dashboard.
     */
    public function loan()
    {
        return $this->hasOne(Loan::class);
    }
}