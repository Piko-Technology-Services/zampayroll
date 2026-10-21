<?php

namespace App\Models;

use App\Models\Concerns\HasInstallmentSchedule;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    use HasInstallmentSchedule;

    public const STATUSES = [
        'pending_disbursement' => 'Approved — not paid out yet',
        'active'      => 'Active',
        'completed'   => 'Completed',
        'paused'      => 'Paused',
        'written_off' => 'Written Off',
        'cancelled'   => 'Cancelled (never paid out)',
    ];

    public const DISBURSEMENT_METHODS = [
        'bank_transfer' => 'Bank transfer',
        'mobile_money'  => 'Mobile money',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
    ];

    public function isDisbursed(): bool
    {
        return $this->disbursed_at !== null;
    }

    /** Repayments come out of pay only for loans that were actually paid out and are running. */
    public function isDeductible(): bool
    {
        return $this->status === 'active' && $this->isDisbursed() && (float) $this->balance > 0;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    protected $fillable = [
        'employee_id',
        'loan_request_id',
        'principal_amount',
        'balance',
        'payment_plan',
        'payment_plan_note',
        'installment_amount',
        'start_date',
        'next_deduction_date',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'disbursed_at',
        'disbursed_by',
        'disbursement_method',
        'disbursement_reference',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'principal_amount'    => 'decimal:2',
        'balance'             => 'decimal:2',
        'installment_amount'  => 'decimal:2',
        'start_date'          => 'date',
        'next_deduction_date' => 'date',
        'approved_at'         => 'datetime',
        'disbursed_at'        => 'date',
        'cancelled_at'        => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function loanRequest()
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recompute an employee's cached total_loan_balance / has_active_loan
     * from their loans. Call this after ANY loan balance/status change.
     */
    public static function syncEmployeeCache(int $employeeId): void
    {
        $activeLoans = static::where('employee_id', $employeeId)->where('status', 'active');

        Employee::where('id', $employeeId)->update([
            'total_loan_balance' => (clone $activeLoans)->sum('balance'),
            'has_active_loan'    => (clone $activeLoans)->exists(),
        ]);
    }
}